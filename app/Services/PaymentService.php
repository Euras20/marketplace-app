<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentProof;
use App\Models\PaymentVerificationHistory;
use App\Models\OrderStatusHistory;
use Illuminate\Support\Facades\DB;

class PaymentService
{
    public function __construct(
        private StoreContext $storeContext,
        private InventoryService $inventory
    ) {}

    /**
     * Create the initial pending payment record when an order is placed.
     */
    public function createPendingForOrder(Order $order, string $methodCode = 'manual_transfer'): Payment
    {
        $this->assertSameStore($order);

        return Payment::create([
            'store_id' => $order->store_id,
            'order_id' => $order->id,
            'method_code' => $methodCode,
            'amount' => $order->grand_total,
            'currency_code' => 'EUR',
            'status' => Payment::STATUS_PENDING,
        ]);
    }

    /**
     * Customer uploads a receipt. This never verifies payment.
     */
    public function submitProof(Payment $payment, array $fileMeta): PaymentProof
    {
        $this->assertSameStore($payment->order);

        if (!in_array($payment->status, [Payment::STATUS_PENDING, Payment::STATUS_REJECTED])) {
            throw new \RuntimeException('Proof cannot be submitted in the current payment state.');
        }

        $proof = PaymentProof::create([
            'store_id' => $payment->store_id,
            'payment_id' => $payment->id,
            'storage_disk' => $fileMeta['storage_disk'] ?? 'local',
            'storage_path' => $fileMeta['storage_path'],
            'original_filename' => $fileMeta['original_filename'],
            'mime_type' => $fileMeta['mime_type'],
            'file_size' => $fileMeta['file_size'],
        ]);

        $payment->update([
            'status' => Payment::STATUS_SUBMITTED,
            'submitted_at' => now(),
        ]);

        $payment->order->update([
            'status' => Order::STATUS_PAYMENT_REVIEW,
            'payment_status' => Payment::STATUS_SUBMITTED,
        ]);

        return $proof;
    }

    /**
     * Administrator verifies payment.
     * This single transactional action also approves the order and deducts stock.
     * Must be idempotent.
     */
    public function verify(Payment $payment, int $adminId): Payment
    {
        $order = $payment->order;
        $this->assertSameStore($order);

        return DB::transaction(function () use ($payment, $order, $adminId) {
            $payment = Payment::where('id', $payment->id)->lockForUpdate()->first();
            $order = Order::where('id', $order->id)->lockForUpdate()->first();

            if ($payment->status === Payment::STATUS_VERIFIED) {
                // Idempotent: already verified
                return $payment;
            }

            if ($payment->status !== Payment::STATUS_SUBMITTED) {
                throw new \RuntimeException('Payment is not in a verifiable state.');
            }

            // Deduct stock for every order item
            foreach ($order->items as $item) {
                $this->inventory->deductOnApproval(
                    $item->variant,
                    $item->quantity,
                    $order->id,
                    'admin',
                    $adminId
                );
            }

            $payment->update([
                'status' => Payment::STATUS_VERIFIED,
                'verified_at' => now(),
                'verified_by_admin_id' => $adminId,
            ]);

            $order->update([
                'status' => Order::STATUS_APPROVED,
                'payment_status' => Payment::STATUS_VERIFIED,
                'approved_at' => now(),
            ]);

            PaymentVerificationHistory::create([
                'store_id' => $payment->store_id,
                'payment_id' => $payment->id,
                'decision' => 'verified',
                'admin_user_id' => $adminId,
            ]);

            OrderStatusHistory::create([
                'store_id' => $order->store_id,
                'order_id' => $order->id,
                'from_status' => Order::STATUS_PAYMENT_REVIEW,
                'to_status' => Order::STATUS_APPROVED,
                'actor_type' => 'admin',
                'actor_id' => $adminId,
                'note' => 'Payment verified – order approved',
            ]);

            return $payment->fresh();
        });
    }

    /**
     * Administrator rejects the proof. Order stays unapproved.
     */
    public function reject(Payment $payment, int $adminId, string $reason): Payment
    {
        $order = $payment->order;
        $this->assertSameStore($order);

        return DB::transaction(function () use ($payment, $order, $adminId, $reason) {
            $payment = Payment::where('id', $payment->id)->lockForUpdate()->first();

            $payment->update([
                'status' => Payment::STATUS_REJECTED,
                'rejection_reason' => $reason,
            ]);

            $order->update([
                'payment_status' => Payment::STATUS_REJECTED,
                // order status remains payment_review or reverts according to policy
            ]);

            PaymentVerificationHistory::create([
                'store_id' => $payment->store_id,
                'payment_id' => $payment->id,
                'decision' => 'rejected',
                'reason' => $reason,
                'admin_user_id' => $adminId,
            ]);

            return $payment->fresh();
        });
    }


    /**
     * Mark an order paid when the customer paid without uploading a proof.
     */
    public function markPaidWithoutProof(Order $order, int $adminId): Payment
    {
        $this->assertSameStore($order);

        return DB::transaction(function () use ($order, $adminId) {
            $order = Order::where('id', $order->id)->lockForUpdate()->first();

            $payment = Payment::firstOrCreate(
                ['order_id' => $order->id],
                [
                    'store_id' => $order->store_id,
                    'method_code' => 'bank_transfer',
                    'amount' => $order->grand_total,
                    'currency_code' => $order->currency_code ?? 'EUR',
                    'status' => Payment::STATUS_SUBMITTED,
                ]
            );

            if ($payment->status !== Payment::STATUS_VERIFIED) {
                $payment->update(['status' => Payment::STATUS_SUBMITTED]);
            }

            return $this->verify($payment->fresh(), $adminId);
        });
    }

    private function assertSameStore($model): void
    {
        if ($model->store_id !== $this->storeContext->get()->id) {
            abort(404);
        }
    }
}
