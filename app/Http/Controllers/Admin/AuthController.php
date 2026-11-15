<?php

namespace App\Http\Controllers\Admin;

use App\Models\AdminUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController
{
    public function login(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $admin = AdminUser::where('email', $validated['email'])->first();

        if (!$admin || !Hash::check($validated['password'], $admin->password)) {
            return response()->json(['message' => 'Invalid credentials.'], 401);
        }

        // Scaffold: issue a simple bearer token. In production use Sanctum/Passport.
        $token = bin2hex(random_bytes(32));
        // Store hashed token or use a real token driver; for scaffold we return it.
        // A real implementation would persist it or use Sanctum personal access tokens.

        $admin->forceFill(['last_login_at' => now()])->save();

        return response()->json([
            'token' => $token,
            'admin' => $admin->only(['id', 'name', 'email']),
        ]);
    }

    public function me(Request $request)
    {
        // Scaffold: in a real app this would return the authenticated admin from the guard.
        return response()->json([
            'admin' => auth('admin')->user()?->only(['id', 'name', 'email']),
        ]);
    }
}
