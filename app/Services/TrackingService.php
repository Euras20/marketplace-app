<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderTracking;
use App\Models\OrderStatusHistory;
use Illuminate\Support\Str;

class TrackingService
{
    public function __construct(private StoreContext $storeContext) {}

    /**
     * Generate tracking code + high-entropy token on order approval.
     * Token is returned once (for email); only the hash is stored.
     */
    public function createForOrder(Order $order): array
    {
        $this->assertSameStore($order);

        $code = $this->generateTrackingCode();
        $token = Str::random(64);
        $hash = hash('sha256', $token);

        $tracking = OrderTracking::create([
            'store_id' => $order->store_id,
            'order_id' => $order->id,
            'tracking_code' => $code,
            'tracking_token_hash' => $hash,
            'status' => $order->status,
        ]);

        return [
            'tracking' => $tracking,
            'plain_token' => $token, // send in email, never store
            'tracking_code' => $code,
        ];
    }

    /**
     * Admin updates fulfilment status.
     */
    public function updateStatus(Order $order, string $newStatus, int $adminId): Order
    {
        $this->assertSameStore($order);

        $allowed = [
            Order::STATUS_APPROVED => Order::STATUS_IN_PREPARATION,
            Order::STATUS_IN_PREPARATION => Order::STATUS_SHIPPED,
            Order::STATUS_SHIPPED => Order::STATUS_DELIVERED,
        ];

        if (!isset($allowed[$order->status]) || $allowed[$order->status] !== $newStatus) {
            throw new \RuntimeException('Invalid status transition.');
        }

        $from = $order->status;
        $order->update(['status' => $newStatus]);

        if ($tracking = $order->tracking) {
            $updates = ['status' => $newStatus];
            if ($newStatus === Order::STATUS_SHIPPED) {
                $updates['shipped_at'] = now();
            }
            if ($newStatus === Order::STATUS_DELIVERED) {
                $updates['delivered_at'] = now();
            }
            $tracking->update($updates);
        }

        OrderStatusHistory::create([
            'store_id' => $order->store_id,
            'order_id' => $order->id,
            'from_status' => $from,
            'to_status' => $newStatus,
            'actor_type' => 'admin',
            'actor_id' => $adminId,
        ]);

        return $order->fresh();
    }

    /**
     * Public lookup by secure token. Returns only safe fields.
     */
    public function lookupByToken(string $token): ?array
    {
        $hash = hash('sha256', $token);

        $tracking = OrderTracking::where('tracking_token_hash', $hash)->first();

        if (!$tracking) {
            return null;
        }

        return $this->publicPayload($tracking);
    }

    /**
     * Public lookup by tracking code (rate-limited in production).
     */
    public function lookupByCode(string $code): ?array
    {
        $tracking = OrderTracking::where('tracking_code', $code)->first();

        if (!$tracking) {
            $order = Order::where('order_number', $code)->first();
            if ($order) {
                $tracking = OrderTracking::where('order_id', $order->id)->first();
            }
        }

        if (!$tracking) {
            return null;
        }

        return $this->publicPayload($tracking);
    }

    private function publicPayload(OrderTracking $tracking): array
    {
        $order = $tracking->order;

        return [
            'tracking_code' => $tracking->tracking_code,
            'status' => $tracking->status,
            'shipped_at' => $tracking->shipped_at,
            'delivered_at' => $tracking->delivered_at,
            'order_number' => $order->order_number,
            // deliberately omit: customer details, address, payment, receipts, internal notes
        ];
    }

    private function generateTrackingCode(): string
    {
        do {
            $code = strtoupper(Str::random(10));
        } while (OrderTracking::where('tracking_code', $code)->exists());

        return $code;
    }

    private function assertSameStore(Order $order): void
    {
        if ($order->store_id !== $this->storeContext->get()->id) {
            abort(404);
        }
    }
}
