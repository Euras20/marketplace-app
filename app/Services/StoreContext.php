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
        $host = $request->getHost();

        // Production path: exact domain match
        $store = Store::where('primary_domain', $host)
            ->where('status', 'active')
            ->first();

        if ($store) {
            return $store;
        }

        // Local development fallback
        if (in_array($host, ['localhost', '127.0.0.1', '::1'], true)) {
            $code = $request->query('store')
                ?? $request->header('X-Store-Code')
                ?? env('LOCAL_STORE_CODE', 'agri');

            return Store::where('code', $code)
                ->where('status', 'active')
                ->first()
                ?? Store::where('status', 'active')->orderBy('id')->first();
        }

        return null;
    }
}
