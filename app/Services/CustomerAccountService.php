<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\GuestOrderClaim;
use App\Models\Order;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CustomerAccountService
{
    public function __construct(private StoreContext $storeContext) {}

    /**
     * Optional customer registration (store-scoped).
     */
    public function register(array $data): Customer
    {
        $store = $this->storeContext->get();

        $emailNormalized = strtolower(trim($data['email']));

        if (Customer::where('store_id', $store->id)->where('email_normalized', $emailNormalized)->exists()) {
            throw new \RuntimeException('An account with this email already exists in this store.');
        }

        return Customer::create([
            'store_id' => $store->id,
            'email' => $data['email'],
            'email_normalized' => $emailNormalized,
            'first_name' => $data['first_name'] ?? null,
            'last_name' => $data['last_name'] ?? null,
            'password' => Hash::make($data['password']),
            'account_status' => 'active',
        ]);
    }

    /**
     * Start a guest-order claim. Sends a verification token (email).
     * Claim is only possible within the same store after email ownership is proven.
     */
    public function startGuestClaim(Order $order, string $email): GuestOrderClaim
    {
        $this->assertSameStore($order);

        $token = Str::random(64);

        return GuestOrderClaim::create([
            'store_id' => $order->store_id,
            'order_id' => $order->id,
            'email' => $email,
            'claim_token_hash' => hash('sha256', $token),
        ]);

        // In production: queue email containing the plain token
    }

    /**
     * Complete claim after the customer proves email ownership.
     */
    public function completeGuestClaim(string $plainToken, Customer $customer): Order
    {
        $hash = hash('sha256', $plainToken);

        $claim = GuestOrderClaim::where('claim_token_hash', $hash)
            ->whereNull('claimed_at')
            ->firstOrFail();

        if ($claim->store_id !== $customer->store_id) {
            abort(403, 'Claim must stay within the same store.');
        }

        $order = $claim->order;

        $order->update(['customer_id' => $customer->id]);

        $claim->update([
            'claimed_at' => now(),
            'claimed_by_customer_id' => $customer->id,
        ]);

        return $order;
    }

    private function assertSameStore(Order $order): void
    {
        if ($order->store_id !== $this->storeContext->get()->id) {
            abort(404);
        }
    }
}
