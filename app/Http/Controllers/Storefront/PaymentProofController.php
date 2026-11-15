<?php

namespace App\Http\Controllers\Storefront;

use App\Models\Order;
use App\Models\Payment;
use App\Services\NotificationService;
use App\Services\PaymentService;
use App\Services\StoreContext;
use Illuminate\Http\Request;

class PaymentProofController
{
    public function __construct(
        private StoreContext $storeContext,
        private PaymentService $payments
    ) {}

    /**
     * Customer uploads a receipt for an order.
     * Upload never verifies payment.
     */
    public function store(Request $request, string $orderPublicId)
    {
        $request->validate([
            'receipt' => 'required|file|mimes:jpg,jpeg,png,pdf|max:10240',
        ]);

        $order = Order::where('store_id', $this->storeContext->get()->id)
            ->where('public_id', $orderPublicId)
            ->firstOrFail();

        $payment = Payment::firstOrCreate(
            ['order_id' => $order->id],
            [
                'store_id' => $order->store_id,
                'method_code' => 'bank_transfer',
                'amount' => $order->grand_total,
                'currency_code' => $order->currency_code ?? 'EUR',
                'status' => 'pending',
            ]
        );

        // In production: store file privately, generate randomized path, validate content
        $file = $request->file('receipt');
        $disk = 'local';
        $path = $file->store('receipts', $disk);

        $proof = $this->payments->submitProof($payment, [
            'storage_disk' => $disk,
            'storage_path' => $path,
            'original_filename' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'file_size' => $file->getSize(),
        ]);

        try {
            app(\App\Services\NotificationService::class)->notifyCustomer($order, 'pending_verification');
        } catch (\Throwable $e) {}

        return response()->json([
            'message' => 'Receipt submitted for review.',
            'proof_id' => $proof->id,
        ], 201);
    }
}
