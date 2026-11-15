<?php

namespace App\Http\Controllers\Storefront;

use App\Services\CustomerAccountService;
use App\Services\StoreContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class CustomerAuthController
{
    public function __construct(
        private StoreContext $storeContext,
        private CustomerAccountService $accounts
    ) {}

    public function register(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string|min:8',
            'first_name' => 'nullable|string|max:100',
            'last_name' => 'nullable|string|max:100',
        ]);

        $customer = $this->accounts->register($validated);

        // In production: issue Sanctum token or session
        return response()->json([
            'customer' => $customer->only(['public_id', 'email', 'first_name', 'last_name']),
        ], 201);
    }

    public function login(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $store = $this->storeContext->get();
        $emailNormalized = strtolower(trim($validated['email']));

        $customer = \App\Models\Customer::where('store_id', $store->id)
            ->where('email_normalized', $emailNormalized)
            ->first();

        if (!$customer || !Hash::check($validated['password'], $customer->password)) {
            return response()->json(['message' => 'Invalid credentials.'], 401);
        }

        // In production: issue token
        return response()->json([
            'customer' => $customer->only(['public_id', 'email', 'first_name', 'last_name']),
        ]);
    }
}
