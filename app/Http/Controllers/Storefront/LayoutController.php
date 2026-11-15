<?php

namespace App\Http\Controllers\Storefront;

use App\Models\StoreSetting;
use App\Services\StoreContext;
use Illuminate\Http\Request;

class LayoutController
{
    public function __construct(private StoreContext $storeContext) {}

    public function show(Request $request)
    {
        $store = $this->storeContext->get();

        $settings = StoreSetting::where('store_id', $store->id)
            ->get()
            ->pluck('value', 'key');

        return response()->json([
            'data' => [
                'store' => [
                    'code' => $store->code,
                    'name' => $store->name,
                    'domain' => $store->primary_domain,
                ],
                'header' => [
                    'announcement' => $settings->get('announcement') ?? 'Livraison disponible',
                    'phone' => $settings->get('phone') ?? '',
                    'support_hours' => $settings->get('support_hours') ?? '',
                    'logo_url' => $settings->get('logo_url') ?? null,
                ],
                'footer' => [
                    'legal_text' => $settings->get('legal_text') ?? 'Tous droits réservés.',
                    'payment_methods' => $settings->get('payment_methods') ?? ['CB', 'VISA', 'MASTERCARD'],
                ],
                'settings' => $settings,
            ],
        ]);
    }

    public function paymentInfo()
    {
        $store = $this->storeContext->get();
        $setting = \App\Models\StorePaymentSetting::where('store_id', $store->id)
            ->where('is_enabled', true)
            ->orderBy('id')
            ->first();

        $details = $setting?->account_details ?? [];

        return response()->json([
            'display_name' => $setting?->display_name,
            'instructions' => $setting?->instructions,
            'beneficiary' => $details['beneficiary'] ?? null,
            'iban' => $details['iban'] ?? null,
            'bic' => $details['bic'] ?? null,
        ]);
    }
}
