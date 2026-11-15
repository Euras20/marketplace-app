<?php

namespace App\Http\Middleware;

use App\Models\Store;
use App\Services\StoreContext;
use Closure;
use Illuminate\Http\Request;

class AdminStoreContext
{
    public function __construct(private StoreContext $storeContext) {}

    public function handle(Request $request, Closure $next)
    {
        $code = $request->header('X-Store-Code', 'agri');

        $store = Store::where('code', $code)->where('status', 'active')->first();

        if (!$store) {
            return response()->json(['message' => 'Store not found.'], 404);
        }

        $this->storeContext->set($store);

        return $next($request);
    }
}