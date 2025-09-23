<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Services\FirebaseAuthService;
use Symfony\Component\HttpFoundation\Response;

class FirebaseAuthMiddleware
{
    private FirebaseAuthService $firebaseAuth;

    public function __construct(FirebaseAuthService $firebaseAuth)
    {
        $this->firebaseAuth = $firebaseAuth;
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Get token from Authorization header
        $token = $this->extractTokenFromRequest($request);

        if (!$token) {
            return $this->unauthorizedResponse($request);
        }

        // Verify Firebase token
        $claims = $this->firebaseAuth->verifyToken($token);

        if (!$claims) {
            return $this->unauthorizedResponse($request);
        }

        try {
            // Get or create Laravel user
            $user = $this->firebaseAuth->getOrCreateUser($claims);

            // Authenticate user in Laravel
            Auth::login($user);

            // Add Firebase claims to request for access in controllers
            $request->merge(['firebase_claims' => $claims]);

            return $next($request);

        } catch (\Exception $e) {
            \Log::error('Firebase auth middleware error', [
                'error' => $e->getMessage(),
                'token_preview' => substr($token, 0, 10) . '...'
            ]);

            return $this->unauthorizedResponse($request);
        }
    }

    /**
     * Extract JWT token from request
     */
    private function extractTokenFromRequest(Request $request): ?string
    {
        // Try Authorization header first (Bearer token)
        $authHeader = $request->header('Authorization');
        if ($authHeader && preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            return $matches[1];
        }

        // Try token parameter (for development/testing)
        return $request->input('token');
    }

    /**
     * Return unauthorized response
     */
    private function unauthorizedResponse(Request $request): Response
    {
        if ($request->expectsJson()) {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => 'Valid Firebase authentication token required'
            ], 401);
        }

        // For web requests, redirect to login
        return redirect()->route('login')->with('error', 'Please log in to continue');
    }
}
