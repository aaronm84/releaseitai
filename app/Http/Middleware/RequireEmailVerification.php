<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Services\FirebaseAuthService;

class RequireEmailVerification
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Skip verification check for Firebase auth endpoint (used during account creation)
        if ($request->is('api/auth/firebase') || $request->is('auth/firebase')) {
            return $next($request);
        }

        // Only check verification for Firebase authenticated users
        $firebaseToken = $request->bearerToken() ?? $request->session()->get('firebase_token');

        if ($firebaseToken) {
            $firebaseAuth = app(FirebaseAuthService::class);
            $claims = $firebaseAuth->verifyToken($firebaseToken);

            if ($claims && !$firebaseAuth->isEmailVerified($claims)) {
                // For API requests, return JSON error
                if ($request->expectsJson()) {
                    return response()->json([
                        'message' => 'Email verification required',
                        'redirect' => '/verify-email?email=' . urlencode($claims['email'] ?? '')
                    ], 403);
                }

                // For web requests, redirect to verification page
                return redirect()->route('verify-email', ['email' => $claims['email'] ?? '']);
            }
        }

        return $next($request);
    }
}
