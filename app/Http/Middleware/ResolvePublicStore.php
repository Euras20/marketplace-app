<?php

namespace App\Http\Middleware;

use App\Services\StoreContext;
use Closure;
use Illuminate\Http\Request;

class ResolvePublicStore
{
    public function __construct(private StoreContext $storeContext) {}

    public function handle(Request $request, Closure $next)
    {
        $store = $this->storeContext->resolveFromRequest($request);

        if (!$store) {
            abort(404, 'Store not found for this domain.');
        }

        $this->storeContext->set($store);

        return $next($request);
    }
}
