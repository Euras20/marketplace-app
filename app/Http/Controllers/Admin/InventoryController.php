<?php

namespace App\Http\Controllers\Admin;

use App\Models\ProductVariant;
use App\Services\InventoryService;
use App\Services\StoreContext;
use Illuminate\Http\Request;

class InventoryController
{
    public function __construct(
        private StoreContext $storeContext,
        private InventoryService $inventory
    ) {}

    public function index()
    {
        $store = $this->storeContext->get();

        $records = \App\Models\Inventory::where('store_id', $store->id)
            ->with('variant.product')
            ->paginate(50);

        return response()->json($records);
    }

    public function show(ProductVariant $variant)
    {
        $inventory = $this->inventory->forVariant($variant);

        return response()->json([
            'on_hand' => $inventory->on_hand,
            'reserved' => $inventory->reserved,
            'available' => $inventory->available,
        ]);
    }

    public function adjust(Request $request, ProductVariant $variant)
    {
        $validated = $request->validate([
            'delta' => 'required|integer',
            'reason' => 'required|string|max:500',
        ]);

        $inventory = $this->inventory->adjust(
            $variant,
            $validated['delta'],
            $validated['reason'],
            'admin',
            auth()->id()
        );

        return response()->json([
            'on_hand' => $inventory->on_hand,
            'reserved' => $inventory->reserved,
            'available' => $inventory->available,
        ]);
    }

    public function movements(ProductVariant $variant)
    {
        $this->inventory->forVariant($variant); // store check

        $movements = \App\Models\InventoryMovement::where('store_id', $variant->store_id)
            ->where('product_variant_id', $variant->id)
            ->orderBy('created_at', 'desc')
            ->paginate(50);

        return response()->json($movements);
    }
}
