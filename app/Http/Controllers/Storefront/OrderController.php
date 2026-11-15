<?php

namespace App\Http\Controllers\Storefront;

use App\Services\CartService;
use App\Services\NotificationService;
use App\Services\OrderService;
use App\Services\StoreContext;
use Illuminate\Http\Request;

class OrderController
{
    public function __construct(
        private StoreContext $storeContext,
        private CartService $carts,
        private OrderService $orders,
        private NotificationService $notifications
    ) {}

    public function store(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'phone' => 'nullable|string',
            'name' => 'required|string|max:255',
            'address_line_1' => 'required|string',
            'address_line_2' => 'nullable|string',
            'postal_code' => 'required|string',
            'city' => 'required|string',
            'region' => 'nullable|string',
            'country_code' => 'required|string|size:2',
        ]);

        $guestToken = $request->header('X-Guest-Cart-Token');
        $customerId = null;
        try { $customerId = auth('customer')->id(); } catch (\Throwable $e) {}

        $cart = $this->carts->current($guestToken, $customerId);

        if ($cart->items()->count() === 0) {
            return response()->json(['message' => 'Cart is empty.'], 422);
        }

        $contact = [
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'name' => $validated['name'],
        ];

        $address = [
            'address_line_1' => $validated['address_line_1'],
            'address_line_2' => $validated['address_line_2'] ?? null,
            'postal_code' => $validated['postal_code'],
            'city' => $validated['city'],
            'region' => $validated['region'] ?? null,
            'country_code' => $validated['country_code'],
        ];

        $order = $this->orders->createFromCart($cart, $contact, $address);

        try { $this->notifications->notifyAdminNewOrder($order); } catch (\Throwable $e) {}

        return response()->json([
            'order' => $order,
            'payment_instructions' => 'Upload receipt after transfer (Phase 6).',
        ], 201);
    }

    public function notifyInvoice(string $publicId)
    {
        $order = \App\Models\Order::where('store_id', $this->storeContext->get()->id)
            ->where('public_id', $publicId)
            ->firstOrFail();

        $this->notifications->notifyCustomer($order, 'invoice');

        return response()->json(['message' => 'Invoice sent.']);
    }

    public function show(string $publicId)
    {
        $order = \App\Models\Order::where('store_id', $this->storeContext->get()->id)
            ->where('public_id', $publicId)
            ->with('items')
            ->firstOrFail();

        // In production this would also require a guest order secret or authenticated ownership
        return response()->json($order);
    }
}
