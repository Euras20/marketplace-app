<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class RateLimitSensitiveEndpoints
{
    public function handle(Request $request, Closure $next, string $key = 'default', int $maxAttempts = 10)
    {
        $limiterKey = $key . '|' . $request->ip();

        if (RateLimiter::tooManyAttempts($limiterKey, $maxAttempts)) {
            return response()->json([
                'message' => 'Too many attempts. Please try again later.',
            ], 429);
        }

        RateLimiter::hit($limiterKey, 60);

        return $next($request);
    }
}
