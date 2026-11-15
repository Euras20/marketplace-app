<?php

namespace App\Services;

use App\Mail\OrderMail;
use App\Models\AdminUser;
use App\Models\EmailLog;
use App\Models\Order;
use App\Models\StorePaymentSetting;
use App\Models\StoreSetting;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class NotificationService
{
    public function sendOrderEmail(Order $order, string $templateKey, string $recipient): ?EmailLog
    {
        $order->loadMissing('items');

        $log = EmailLog::create([
            'store_id' => $order->store_id,
            'order_id' => $order->id,
            'recipient' => $recipient,
            'template_key' => $templateKey,
            'status' => EmailLog::STATUS_QUEUED,
        ]);

        try {
            Mail::to($recipient)->send(new OrderMail(
                $order,
                $templateKey,
                $this->company($order),
                $this->paymentInfo($order),
            ));
            $this->markSent($log);
        } catch (\Throwable $e) {
            $this->markFailed($log, $e->getMessage());
            Log::warning('Order email failed', [
                'order' => $order->id,
                'template' => $templateKey,
                'error' => $e->getMessage(),
            ]);
        }

        return $log;
    }

    public function notifyCustomer(Order $order, string $templateKey): void
    {
        if (!$order->contact_email) {
            return;
        }
        $this->sendOrderEmail($order, $templateKey, $order->contact_email);
    }

    public function notifyAdminNewOrder(Order $order): void
    {
        $admin = AdminUser::query()->value('email');
        if (!$admin) {
            return;
        }
        $this->sendOrderEmail($order, 'admin_new_order', $admin);
    }

    private function company(Order $order): array
    {
        $store = $order->store ?? \App\Models\Store::find($order->store_id);
        $settings = StoreSetting::where('store_id', $order->store_id)->pluck('value', 'key');

        return [
            'name' => $store->name ?? 'Marketplace',
            'tagline' => $settings->get('tagline') ?? 'Votre commande',
            'support' => $settings->get('support_email') ?? $settings->get('email') ?? 'support@example.com',
            'phone' => $settings->get('phone') ?? '',
            'address' => $settings->get('address') ?? '',
        ];
    }

    private function paymentInfo(Order $order): array
    {
        $setting = StorePaymentSetting::where('store_id', $order->store_id)
            ->where('is_enabled', true)
            ->orderBy('id')
            ->first();

        $details = $setting?->account_details ?? [];

        return [
            'beneficiary' => $details['beneficiary'] ?? null,
            'iban' => $details['iban'] ?? null,
            'bic' => $details['bic'] ?? null,
            'instructions' => $setting?->instructions,
        ];
    }

    public function queueOrderEmail(Order $order, string $templateKey, string $recipient): EmailLog
    {
        return $this->sendOrderEmail($order, $templateKey, $recipient) ?? EmailLog::create([
            'store_id' => $order->store_id,
            'order_id' => $order->id,
            'recipient' => $recipient,
            'template_key' => $templateKey,
            'status' => EmailLog::STATUS_FAILED,
        ]);
    }

    public function markSent(EmailLog $log, ?string $providerMessageId = null): void
    {
        $log->update([
            'status' => EmailLog::STATUS_SENT,
            'provider_message_id' => $providerMessageId,
            'sent_at' => now(),
        ]);
    }

    public function markFailed(EmailLog $log, string $reason): void
    {
        $log->update([
            'status' => EmailLog::STATUS_FAILED,
            'failure_reason' => $reason,
        ]);
    }
}
