<?php

namespace App\Services;

use App\Models\Store;
use Illuminate\Http\Request;

class StoreContext
{
    private ?Store $current = null;

    public function set(Store $store): void
    {
        if (!$store->isActive()) {
            throw new \RuntimeException('Store is not active.');
        }
        $this->current = $store;
    }

    public function get(): Store
    {
        if ($this->current === null) {
            throw new \RuntimeException('No active store context.');
        }
        return $this->current;
    }

    public function has(): bool
    {
        return $this->current !== null;
    }

    public function clear(): void
    {
        $this->current = null;
    }

    /**
     * Resolve store from trusted host for public storefront requests.
     * Local-dev fallback: localhost / 127.0.0.1 picks the first active store
     * (or the one matching ?store=agri / X-Store-Code header).
     */
public function resolveFromRequest(Request $request): ?Store
{
    // Prefer explicit store code (sent by frontends)
    $code = $request->header('X-Store-Code')
        ?? $request->query('store');

    if ($code) {
        $store = Store::where('code', $code)
            ->where('status', 'active')
            ->first();
        if ($store) {
            return $store;
        }
    }

    // Fallback to domain match (useful if you later put stores on their own domains)
    $host = $request->getHost();
    return Store::where('primary_domain', $host)
        ->where('status', 'active')
        ->first();
}

        return null;
    }
}
