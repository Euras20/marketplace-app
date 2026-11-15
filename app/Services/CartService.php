<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CartService
{
    public function __construct(
        private StoreContext $storeContext,
        private InventoryService $inventory
    ) {}

    /**
     * Resolve or create the current cart (guest token or authenticated customer).
     */
    public function current(?string $guestToken = null, ?int $customerId = null): Cart
    {
        $store = $this->storeContext->get();

        if ($customerId) {
            return Cart::firstOrCreate(
                [
                    'store_id' => $store->id,
                    'customer_id' => $customerId,
                    'status' => Cart::STATUS_ACTIVE,
                ],
                ['expires_at' => now()->addDays(7)]
            );
        }

        if ($guestToken) {
            $hash = hash('sha256', $guestToken);
            $cart = Cart::where('store_id', $store->id)
                ->where('guest_token_hash', $hash)
                ->where('status', Cart::STATUS_ACTIVE)
                ->first();

            if ($cart) {
                return $cart;
            }
        }

        $plainToken = Str::random(64);
        $cart = Cart::create([
            'store_id' => $store->id,
            'guest_token_hash' => hash('sha256', $plainToken),
            'status' => Cart::STATUS_ACTIVE,
            'expires_at' => now()->addDays(7),
        ]);

        // In a real response the plain token is returned once to the client (cookie / header)
        $cart->setAttribute('plain_guest_token', $plainToken);

        return $cart;
    }

    /**
     * Add or update a variant in the cart.
     * Price and availability are always reloaded from the backend.
     */
    public function addItem(Cart $cart, ProductVariant $variant, int $quantity, ?array $options = null): CartItem
    {
        $this->assertSameStore($cart, $variant);

        if (!$variant->is_active || $variant->product->status !== 'published') {
            throw new \RuntimeException('Variant is not purchasable.');
        }

        $inventory = $this->inventory->forVariant($variant);
        if ($inventory->available < $quantity) {
            throw new \RuntimeException('Insufficient available stock.');
        }

        $existing = CartItem::where('cart_id', $cart->id)
            ->where('product_variant_id', $variant->id)
            ->first();

        if ($existing) {
            $update = ['quantity' => $existing->quantity + $quantity];
            // Only touch options when the caller sent them (adult add-ons).
            // Agri never sends options, so its cart items stay unchanged.
            if ($options !== null) {
                $update['options'] = $options;
            }
            $existing->update($update);
            $item = $existing;
        } else {
            $attrs = [
                'cart_id' => $cart->id,
                'product_variant_id' => $variant->id,
                'store_id' => $cart->store_id,
                'quantity' => $quantity,
            ];
            if ($options !== null) {
                $attrs['options'] = $options;
            }
            $item = CartItem::create($attrs);
        }

        return $item;
    }

    public function updateQuantity(CartItem $item, int $quantity): CartItem
    {
        if ($quantity <= 0) {
            $item->delete();
            return $item;
        }

        $inventory = $this->inventory->forVariant($item->variant);
        if ($inventory->available < $quantity) {
            throw new \RuntimeException('Insufficient available stock.');
        }

        $item->update(['quantity' => $quantity]);

        return $item;
    }

    public function removeItem(CartItem $item): void
    {
        $item->delete();
    }

    /**
     * Recalculate cart totals from current catalogue prices (never trust frontend).
     */
    public function totals(Cart $cart): array
    {
        $cart->load('items.variant');

        $subtotal = 0;
        foreach ($cart->items as $item) {
            $subtotal += $item->variant->price * $item->quantity;
        }

        return [
            'subtotal' => $subtotal,
            'currency' => 'EUR',
            'item_count' => $cart->items->sum('quantity'),
        ];
    }

    private function assertSameStore(Cart $cart, ProductVariant $variant): void
    {
        $storeId = $this->storeContext->get()->id;

        if ($cart->store_id !== $storeId || $variant->store_id !== $storeId) {
            abort(404);
        }
    }
}
