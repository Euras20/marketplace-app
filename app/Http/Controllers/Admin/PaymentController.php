<?php

namespace App\Http\Controllers\Admin;

use App\Models\Payment;
use App\Services\PaymentService;
use App\Services\StoreContext;
use Illuminate\Http\Request;

class PaymentController
{
    public function __construct(
        private StoreContext $storeContext,
        private PaymentService $payments
    ) {}

    public function index()
    {
        $store = $this->storeContext->get();

        $payments = Payment::where('store_id', $store->id)
            ->with('order')
            ->whereIn('status', [Payment::STATUS_SUBMITTED, Payment::STATUS_VERIFIED, Payment::STATUS_REJECTED])
            ->orderBy('submitted_at', 'desc')
            ->paginate(25);

        return response()->json($payments);
    }

    public function show(Payment $payment)
    {
        if ($payment->store_id !== $this->storeContext->get()->id) {
            abort(404);
        }

        return response()->json($payment->load(['order.items', 'proofs', 'verificationHistory']));
    }

    public function verify(Payment $payment)
    {
        $payment = $this->payments->verify($payment, auth()->id() ?? 1 ?? 1);

        try {
            $order = $payment->order ?? \App\Models\Order::find($payment->order_id);
            if ($order) {
                app(\App\Services\NotificationService::class)->notifyCustomer($order, 'receipt');
            }
        } catch (\Throwable $e) {}

        return response()->json($payment);
    }

    public function markPaid(\App\Models\Order $order)
    {
        $adminId = 1;
        try { $adminId = auth()->id() ?? 1; } catch (\Throwable $e) {}
        $payment = $this->payments->markPaidWithoutProof($order, $adminId);
        try {
            app(\App\Services\NotificationService::class)->notifyCustomer($order->fresh(), 'receipt');
        } catch (\Throwable $e) {}
        return response()->json($payment);
    }

    public function reject(Request $request, Payment $payment)
    {
        $validated = $request->validate([
            'reason' => 'required|string|max:1000',
        ]);

        $adminId = 1;
        try { $adminId = auth()->id() ?? 1; } catch (\Throwable $e) {}
        $payment = $this->payments->reject($payment, $adminId, $validated['reason']);

        return response()->json($payment);
    }

    public function proof(Payment $payment, \App\Models\PaymentProof $proof)
    {
        if ($proof->payment_id !== $payment->id || $proof->store_id !== $this->storeContext->get()->id) {
            abort(404);
        }
        $disk = $proof->storage_disk ?: 'local';
        if (!\Illuminate\Support\Facades\Storage::disk($disk)->exists($proof->storage_path)) {
            abort(404, 'File missing');
        }
        $mime = $proof->mime_type ?: 'image/jpeg';
        if ($mime === 'application/octet-stream') {
            $ext = strtolower(pathinfo($proof->original_filename ?: '', PATHINFO_EXTENSION));
            $mime = match ($ext) {
                'png' => 'image/png',
                'jpg', 'jpeg' => 'image/jpeg',
                'pdf' => 'application/pdf',
                'webp' => 'image/webp',
                default => 'image/jpeg',
            };
        }
        $absolute = \Illuminate\Support\Facades\Storage::disk($disk)->path($proof->storage_path);
        return response()->file($absolute, [
            'Content-Type' => $mime,
            'Content-Disposition' => 'inline; filename="' . ($proof->original_filename ?: 'proof') . '"',
        ]);
    }

}
