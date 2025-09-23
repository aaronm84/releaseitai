<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RegenerateSessionOnAuth
{
    /**
     * Handle an incoming request to regenerate session on authentication
     * to prevent session fixation attacks.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Check if user just authenticated in this request
        if (Auth::check() && $this->userJustAuthenticated($request)) {
            // Regenerate session ID to prevent session fixation
            $request->session()->regenerate();

            // Optionally regenerate CSRF token as well for extra security
            $request->session()->regenerateToken();
        }

        return $response;
    }

    /**
     * Determine if the user just authenticated in this request
     */
    protected function userJustAuthenticated(Request $request): bool
    {
        // Check if this is a login attempt that succeeded
        return $request->isMethod('POST')
            && in_array($request->path(), ['login', 'api/login', 'auth/login'])
            && Auth::check();
    }
}