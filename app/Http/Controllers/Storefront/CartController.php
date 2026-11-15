<?php

namespace App\Http\Controllers\Storefront;

use App\Models\ProductVariant;
use App\Services\CartService;
use App\Services\StoreContext;
use Illuminate\Http\Request;

class CartController
{
    public function __construct(
        private StoreContext $storeContext,
        private CartService $carts
    ) {}

    public function show(Request $request)
    {
        $guestToken = $request->header('X-Guest-Cart-Token');
        $customerId = null;
        try { $customerId = auth('customer')->id(); } catch (\Throwable $e) {}

        $cart = $this->carts->current($guestToken, $customerId);
        $totals = $this->carts->totals($cart);

        return response()->json([
            'cart' => $cart->load(['items.variant.product', 'items.variant.product.media']),
            'totals' => $totals,
            'guest_token' => $cart->plain_guest_token ?? null,
        ]);
    }

    public function addItem(Request $request)
    {
        $validated = $request->validate([
            'variant_id' => 'required|exists:product_variants,id',
            'quantity' => 'required|integer|min:1',
            'options' => 'nullable|array',
        ]);

        $guestToken = $request->header('X-Guest-Cart-Token');
        $customerId = null;
        try { $customerId = auth('customer')->id(); } catch (\Throwable $e) {}

        $cart = $this->carts->current($guestToken, $customerId);
        $variant = ProductVariant::findOrFail($validated['variant_id']);

        $item = $this->carts->addItem($cart, $variant, $validated['quantity'], $validated['options'] ?? null);

        return response()->json([
            'item' => $item->load('variant'),
            'totals' => $this->carts->totals($cart->fresh()),
            'guest_token' => $cart->plain_guest_token ?? null,
        ], 201);
    }

    public function updateItem(Request $request, int $itemId)
    {
        $validated = $request->validate([
            'quantity' => 'required|integer|min:0',
        ]);

        $item = \App\Models\CartItem::where('store_id', $this->storeContext->get()->id)
            ->findOrFail($itemId);

        $this->carts->updateQuantity($item, $validated['quantity']);

        return response()->json([
            'totals' => $this->carts->totals($item->cart),
        ]);
    }

    public function removeItem(int $itemId)
    {
        $item = \App\Models\CartItem::where('store_id', $this->storeContext->get()->id)
            ->findOrFail($itemId);

        $this->carts->removeItem($item);

        return response()->noContent();
    }

    public function clear(Request $request)
    {
        $guestToken = $request->header('X-Guest-Cart-Token');
        $customerId = null;
        try { $customerId = auth('customer')->id(); } catch (\Throwable $e) {}

        $cart = $this->carts->current($guestToken, $customerId);
        $cart->items()->delete();

        return response()->noContent();
    }
}
