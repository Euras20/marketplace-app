<?php

namespace App\Http\Controllers\Admin;

use App\Models\Store;
use App\Services\StoreContext;
use Illuminate\Http\Request;

class StoreContextController
{
    public function index()
    {
        return Store::where('status', 'active')
            ->get(['public_id', 'name', 'code', 'primary_domain']);
    }

    public function set(Request $request, StoreContext $context)
    {
        $validated = $request->validate([
            'store_code' => 'required|string|exists:stores,code',
        ]);

        $store = Store::where('code', $validated['store_code'])
            ->where('status', 'active')
            ->firstOrFail();

        $context->set($store);

        // In a real app this would be stored in the admin session
        session(['admin_store_id' => $store->id]);

        return response()->json([
            'data' => [
                'code' => $store->code,
                'name' => $store->name,
            ],
        ]);
    }

    public function current(StoreContext $context)
    {
        if (!$context->has()) {
            return response()->json(['data' => null], 404);
        }

        $store = $context->get();

        return response()->json([
            'data' => [
                'code' => $store->code,
                'name' => $store->name,
            ],
        ]);
    }
}
