<?php

namespace App\Http\Controllers\Admin;

use App\Models\StoreSetting;
use App\Models\StorePaymentSetting;
use App\Services\AuditService;
use App\Services\StoreContext;
use Illuminate\Http\Request;

class SettingsController
{
    public function __construct(
        private StoreContext $storeContext,
        private AuditService $audit
    ) {}

    public function index()
    {
        $store = $this->storeContext->get();

        $settings = StoreSetting::where('store_id', $store->id)->get();

        return response()->json($settings);
    }

    public function update(Request $request)
    {
        $store = $this->storeContext->get();

        $validated = $request->validate([
            'settings' => 'required|array',
        ]);

        foreach ($validated['settings'] as $key => $value) {
            StoreSetting::updateOrCreate(
                ['store_id' => $store->id, 'key' => $key],
                ['value' => $value]
            );
        }

        $this->audit->log(
            'store_settings_updated',
            StoreSetting::class,
            null,
            ['keys' => array_keys($validated['settings'])],
            auth()->id(),
            $store->id,
            $request
        );

        return response()->json(['message' => 'Settings updated.']);
    }

    public function delivery()
    {
        $store = $this->storeContext->get();

        $setting = StoreSetting::where('store_id', $store->id)
            ->where('key', 'delivery_fee')
            ->first();

        return response()->json($setting?->value ?? ['amount' => 0, 'free_threshold' => null]);
    }

    public function updateDelivery(Request $request)
    {
        $store = $this->storeContext->get();

        $validated = $request->validate([
            'amount' => 'required|numeric|min:0',
            'free_threshold' => 'nullable|numeric|min:0',
        ]);

        StoreSetting::updateOrCreate(
            ['store_id' => $store->id, 'key' => 'delivery_fee'],
            ['value' => $validated]
        );

        $this->audit->log(
            'delivery_fee_updated',
            StoreSetting::class,
            null,
            $validated,
            auth()->id(),
            $store->id,
            $request
        );

        return response()->json(['message' => 'Delivery fee updated. Affects future orders only.']);
    }

    public function payment()
    {
        $store = $this->storeContext->get();

        $settings = StorePaymentSetting::where('store_id', $store->id)->get();

        return response()->json($settings);
    }

    public function updatePayment(Request $request)
    {
        $store = $this->storeContext->get();

        $validated = $request->validate([
            'payment_method_code' => 'required|string',
            'display_name' => 'required|string',
            'instructions' => 'nullable|string',
            'account_details' => 'nullable|array',
            'is_enabled' => 'boolean',
        ]);

        $setting = StorePaymentSetting::updateOrCreate(
            [
                'store_id' => $store->id,
                'payment_method_code' => $validated['payment_method_code'],
            ],
            $validated
        );

        $this->audit->log(
            'payment_settings_updated',
            StorePaymentSetting::class,
            $setting->id,
            ['method' => $validated['payment_method_code']],
            auth()->id(),
            $store->id,
            $request
        );

        return response()->json($setting);
    }
}
