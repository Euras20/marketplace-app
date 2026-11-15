<?php

namespace App\Http\Middleware;

use App\Services\AgeAssuranceService;
use App\Services\StoreContext;
use Closure;
use Illuminate\Http\Request;

class RequireAgeAssurance
{
    public function __construct(
        private StoreContext $storeContext,
        private AgeAssuranceService $ageAssurance
    ) {}

    public function handle(Request $request, Closure $next)
    {
        $store = $this->storeContext->get();

        // Only enforce on the adult store
        if ($store->code !== 'adult') {
            return $next($request);
        }

        // Subject identity: authenticated customer or guest token
        $customer = auth('customer')->user();
        if ($customer) {
            $subjectType = 'customer';
            $subjectId = $customer->public_id;
        } else {
            $guestToken = $request->header('X-Guest-Cart-Token');
            if (!$guestToken) {
                abort(403, 'Age assurance required.');
            }
            $subjectType = 'guest_token_hash';
            $subjectId = hash('sha256', $guestToken);
        }

        if (!$this->ageAssurance->isEligible($subjectType, $subjectId)) {
            abort(403, 'Valid age assurance is required to access this content.');
        }

        return $next($request);
    }
}
