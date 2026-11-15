<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\StoreSetting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OrderService
{
    public function __construct(
        private StoreContext $storeContext,
        private InventoryService $inventory,
        private CartService $carts
    ) {}

    /**
     * Create an order from a cart.
     * Prices, delivery fee and stock reservation are calculated and locked server-side.
     */
    public function createFromCart(Cart $cart, array $contact, array $deliveryAddress): Order
    {
        $store = $this->storeContext->get();

        if ($cart->store_id !== $store->id) {
            abort(404);
        }

        return DB::transaction(function () use ($cart, $contact, $deliveryAddress, $store) {
            $cart->load('items.variant.product');

            $subtotal = 0;
            $lines = [];

            foreach ($cart->items as $item) {
                $variant = $item->variant;

                if (!$variant->is_active || $variant->product->status !== 'published') {
                    throw new \RuntimeException('One or more items are no longer purchasable.');
                }

                // Reserve stock under lock
                $this->inventory->reserve($variant, $item->quantity);

                $unitPrice = $variant->price;
                $options = $item->options ?? [];
                $addonTotal = 0;
                if (!empty($options['addons']) && is_array($options['addons'])) {
                    foreach ($options['addons'] as $addon) {
                        $addonTotal += (float) ($addon['price'] ?? 0);
                    }
                }
                $unitPrice = $unitPrice + $addonTotal;
                $lineTotal = $unitPrice * $item->quantity;
                $subtotal += $lineTotal;

                $lines[] = [
                    'variant' => $variant,
                    'quantity' => $item->quantity,
                    'unit_price' => $unitPrice,
                    'line_total' => $lineTotal,
                    'options' => $options,
                ];
            }

            $deliveryFee = $this->resolveDeliveryFee($store->id);
            $grandTotal = $subtotal + $deliveryFee;

            $order = Order::create([
                'store_id' => $store->id,
                'customer_id' => $cart->customer_id,
                'order_number' => $this->generateOrderNumber($store->id),
                'status' => Order::STATUS_PENDING_PAYMENT,
                'payment_status' => 'pending',
                'currency_code' => 'EUR',
                'subtotal' => $subtotal,
                'delivery_fee' => $deliveryFee,
                'discount_total' => 0,
                'grand_total' => $grandTotal,
                'contact_email' => $contact['email'] ?? null,
                'contact_phone' => $contact['phone'] ?? null,
                'contact_name' => $contact['name'] ?? null,
                'delivery_address_snapshot' => $deliveryAddress,
                'reservation_expires_at' => now()->addHours(24),
            ]);

            foreach ($lines as $line) {
                OrderItem::create([
                    'store_id' => $store->id,
                    'order_id' => $order->id,
                    'product_id' => $line['variant']->product_id,
                    'product_variant_id' => $line['variant']->id,
                    'sku_snapshot' => $line['variant']->sku,
                    'product_name_snapshot' => $line['variant']->product->name,
                    'variant_snapshot' => $line['variant']->variant_name,
                    'options_snapshot' => $line['options'] ?? null,
                    'unit_price' => $line['unit_price'],
                    'quantity' => $line['quantity'],
                    'line_total' => $line['line_total'],
                ]);
            }

            try {
                app(\App\Services\TrackingService::class)->createForOrder($order);
            } catch (\Throwable $e) {
                // tracking optional if table missing
            }

            OrderStatusHistory::create([
                'store_id' => $store->id,
                'order_id' => $order->id,
                'from_status' => null,
                'to_status' => Order::STATUS_PENDING_PAYMENT,
                'actor_type' => 'customer',
                'note' => 'Order created from cart',
            ]);

            $cart->update(['status' => 'converted']);

            return $order->load('items');
        });
    }

    private function resolveDeliveryFee(int $storeId): float
    {
        $setting = StoreSetting::where('store_id', $storeId)
            ->where('key', 'delivery_fee')
            ->first();

        if (!$setting) {
            return 0;
        }

        $value = $setting->value;

        return is_array($value) ? (float) ($value['amount'] ?? 0) : (float) $value;
    }

    private function generateOrderNumber(int $storeId): string
    {
        $prefix = strtoupper(substr($this->storeContext->get()->code, 0, 3));
        $seq = Order::where('store_id', $storeId)->count() + 1;

        return $prefix . '-' . str_pad($seq, 6, '0', STR_PAD_LEFT);
    }
}
