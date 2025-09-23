<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\FirebaseAuthService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Register a new user
     */
    public function register(Request $request): JsonResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'title' => ['sometimes', 'string', 'max:255'],
            'company' => ['sometimes', 'string', 'max:255'],
            'department' => ['sometimes', 'string', 'max:255'],
            'phone' => ['sometimes', 'string', 'max:255'],
            'slack_handle' => ['sometimes', 'string', 'max:255'],
            'teams_handle' => ['sometimes', 'string', 'max:255'],
            'preferred_communication_channel' => ['sometimes', 'in:email,slack,teams,phone'],
            'communication_frequency' => ['sometimes', 'in:daily,weekly,as_needed'],
            'timezone' => ['sometimes', 'string', 'max:255'],
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'title' => $request->title,
            'company' => $request->company,
            'department' => $request->department,
            'phone' => $request->phone,
            'slack_handle' => $request->slack_handle,
            'teams_handle' => $request->teams_handle,
            'preferred_communication_channel' => $request->preferred_communication_channel ?? 'email',
            'communication_frequency' => $request->communication_frequency ?? 'as_needed',
            'timezone' => $request->timezone,
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'user' => $user->fresh(),
            'token' => $token
        ], 201);
    }

    /**
     * Login user
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ])->status(422);
        }

        // Revoke existing tokens
        $user->tokens()->delete();

        // Create new token
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token
        ]);
    }

    /**
     * Logout user
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Successfully logged out'
        ]);
    }

    /**
     * Get user profile
     */
    public function user(Request $request): JsonResponse
    {
        return response()->json($request->user());
    }

    /**
     * Update user profile
     */
    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();

        $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'string', 'email', 'max:255', 'unique:users,email,' . $user->id],
            'title' => ['sometimes', 'string', 'max:255'],
            'company' => ['sometimes', 'string', 'max:255'],
            'department' => ['sometimes', 'string', 'max:255'],
            'phone' => ['sometimes', 'string', 'max:255'],
            'slack_handle' => ['sometimes', 'string', 'max:255'],
            'teams_handle' => ['sometimes', 'string', 'max:255'],
            'preferred_communication_channel' => ['sometimes', 'in:email,slack,teams,phone'],
            'communication_frequency' => ['sometimes', 'in:daily,weekly,as_needed'],
            'influence_level' => ['sometimes', 'in:low,medium,high'],
            'support_level' => ['sometimes', 'in:opponent,neutral,supporter,champion'],
            'timezone' => ['sometimes', 'string', 'max:255'],
            'is_available' => ['sometimes', 'boolean'],
            'unavailable_until' => ['sometimes', 'date'],
            'tags' => ['sometimes', 'array'],
            'stakeholder_notes' => ['sometimes', 'string'],
        ]);

        $user->update($request->only([
            'name',
            'email',
            'title',
            'company',
            'department',
            'phone',
            'slack_handle',
            'teams_handle',
            'preferred_communication_channel',
            'communication_frequency',
            'influence_level',
            'support_level',
            'timezone',
            'is_available',
            'unavailable_until',
            'tags',
            'stakeholder_notes',
        ]));

        return response()->json($user->fresh());
    }

    /**
     * Update user password
     */
    public function updatePassword(Request $request): JsonResponse
    {
        $user = $request->user();

        $request->validate([
            'current_password' => ['required', function ($attribute, $value, $fail) use ($user) {
                if (!Hash::check($value, $user->password)) {
                    $fail('The current password is incorrect.');
                }
            }],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user->update([
            'password' => Hash::make($request->password)
        ]);

        return response()->json([
            'message' => 'Password updated successfully'
        ]);
    }

    /**
     * Authenticate with Firebase token
     */
    public function firebase(Request $request): JsonResponse
    {
        $request->validate([
            'token' => ['required', 'string'],
            'profile_data' => ['sometimes', 'array'],
            'profile_data.title' => ['sometimes', 'string', 'max:255'],
            'profile_data.source' => ['sometimes', 'string', 'max:255'],
        ]);

        $firebaseAuth = app(FirebaseAuthService::class);
        $claims = $firebaseAuth->verifyToken($request->token);

        if (!$claims) {
            return response()->json([
                'message' => 'Invalid Firebase token'
            ], 401);
        }

        $user = $firebaseAuth->getOrCreateUser($claims, $request->input('profile_data', []));

        // Create Sanctum token for API usage
        $token = $user->createToken('firebase_auth_token')->plainTextToken;

        // Also establish Laravel session for web routes
        Auth::login($user, true); // true = remember me

        return response()->json([
            'user' => $user,
            'token' => $token
        ]);
    }
}