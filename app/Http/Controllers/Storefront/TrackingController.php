<?php

namespace App\Http\Controllers\Storefront;

use App\Services\TrackingService;
use Illuminate\Http\Request;

class TrackingController
{
    public function __construct(private TrackingService $tracking) {}

    /**
     * Secure tracking link (token in URL).
     */
    public function byToken(string $token)
    {
        $payload = $this->tracking->lookupByToken($token);

        if (!$payload) {
            abort(404);
        }

        return response()->json($payload);
    }

    /**
     * Tracking code lookup (must be rate-limited).
     */
    public function byCode(Request $request)
    {
        $validated = $request->validate([
            'tracking_code' => 'required|string|max:20',
        ]);

        $payload = $this->tracking->lookupByCode($validated['tracking_code']);

        if (!$payload) {
            // Non-disclosing response
            return response()->json(['message' => 'Tracking information not found.'], 404);
        }

        return response()->json($payload);
    }
}
