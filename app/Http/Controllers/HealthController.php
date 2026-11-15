<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;

class HealthController
{
    public function __invoke()
    {
        try {
            DB::select('select 1');
            $db = 'ok';
        } catch (\Throwable $e) {
            $db = 'error';
        }

        return response()->json([
            'status' => $db === 'ok' ? 'ok' : 'degraded',
            'database' => $db,
            'timestamp' => now()->toIso8601String(),
        ]);
    }
}
