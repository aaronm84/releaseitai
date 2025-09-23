<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\MagicLink;
use App\Mail\MagicLinkMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class MagicLinkController extends Controller
{
    /**
     * Request a magic link
     */
    public function request(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email|max:255',
        ]);

        $email = $request->email;

        // Rate limiting - 5 attempts per minute per email
        $key = 'magic-link-request:' . $email;
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);
            throw ValidationException::withMessages([
                'email' => "Too many magic link requests. Please try again in {$seconds} seconds.",
            ])->status(429);
        }

        RateLimiter::hit($key, 60); // 1 minute window

        // Find or create user
        $user = User::firstOrCreate(
            ['email' => $email],
            ['name' => explode('@', $email)[0]] // Use email prefix as default name
        );

        // Delete any existing magic links for this user
        MagicLink::where('user_id', $user->id)->delete();

        // Create new magic link
        $magicLink = MagicLink::create([
            'user_id' => $user->id,
            'email' => $email,
            'token' => MagicLink::generateToken(),
            'expires_at' => now()->addMinutes(15), // 15 minutes expiry
        ]);

        // Send email
        Mail::to($email)->send(new MagicLinkMail($magicLink));

        return response()->json([
            'message' => 'Magic link sent to your email'
        ]);
    }

    /**
     * Verify and authenticate via magic link
     */
    public function verify(Request $request, string $token)
    {
        $magicLink = MagicLink::where('token', $token)->first();

        if (!$magicLink) {
            return redirect('/login')->with('error', 'Invalid magic link');
        }

        if ($magicLink->isExpired()) {
            $magicLink->delete(); // Clean up expired link
            return redirect('/login')->with('error', 'Magic link has expired');
        }

        // Authenticate user
        Auth::login($magicLink->user);

        // Delete the magic link (one-time use)
        $magicLink->delete();

        // Regenerate session for security
        $request->session()->regenerate();

        return redirect()->intended('/dashboard');
    }
}
