<?php

namespace App\Services;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\SignatureInvalidException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use App\Models\User;
use Exception;

class FirebaseAuthService
{
    private string $projectId;
    private string $jwksUrl = 'https://www.googleapis.com/robot/v1/metadata/x509/securetoken@system.gserviceaccount.com';

    public function __construct()
    {
        $this->projectId = config('services.firebase.project_id');
    }

    /**
     * Verify Firebase JWT token and return user data
     */
    public function verifyToken(string $token): ?array
    {
        try {
            // Get Firebase public keys (cached for performance)
            $publicKeys = $this->getFirebasePublicKeys();

            // Decode and verify the token
            $decoded = JWT::decode($token, $publicKeys);
            $claims = (array) $decoded;

            // Verify Firebase-specific claims
            if (!$this->verifyFirebaseClaims($claims)) {
                return null;
            }

            return $claims;

        } catch (ExpiredException $e) {
            \Log::warning('Firebase token expired', ['error' => $e->getMessage()]);
            return null;
        } catch (SignatureInvalidException $e) {
            \Log::warning('Firebase token signature invalid', ['error' => $e->getMessage()]);
            return null;
        } catch (Exception $e) {
            \Log::error('Firebase token verification failed', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Get or find Laravel user from Firebase claims
     */
    public function getOrCreateUser(array $claims, array $profileData = []): User
    {
        $firebaseUid = $claims['sub'] ?? null;
        $email = $claims['email'] ?? null;
        $name = $claims['name'] ?? null;
        $emailVerified = $claims['email_verified'] ?? false;

        if (!$firebaseUid || !$email) {
            throw new Exception('Invalid Firebase claims: missing uid or email');
        }

        // Try to find user by Firebase UID first
        $user = User::where('firebase_uid', $firebaseUid)->first();

        if (!$user) {
            // Try to find by email
            $user = User::where('email', $email)->first();

            if ($user) {
                // Link existing user to Firebase
                $user->update(['firebase_uid' => $firebaseUid]);
            } else {
                // Create new user with profile data
                $userData = [
                    'name' => $name ?: explode('@', $email)[0],
                    'email' => $email,
                    'firebase_uid' => $firebaseUid,
                    'email_verified_at' => $emailVerified ? now() : null,
                ];

                // Add profile data if provided
                if (!empty($profileData['title'])) {
                    $userData['title'] = $profileData['title'];
                }

                $user = User::create($userData);
            }
        }

        // Update user info if changed
        $updateData = [];

        if ($user->email !== $email) {
            $updateData['email'] = $email;
        }

        if ($user->name !== $name && $name) {
            $updateData['name'] = $name;
        }

        if ($emailVerified && !$user->email_verified_at) {
            $updateData['email_verified_at'] = now();
        }

        // Update title if provided in profile data and user doesn't have one
        if (!empty($profileData['title']) && empty($user->title)) {
            $updateData['title'] = $profileData['title'];
        }

        if (!empty($updateData)) {
            $user->update($updateData);
        }

        return $user;
    }

    /**
     * Get Firebase public keys with caching
     */
    private function getFirebasePublicKeys(): array
    {
        return Cache::remember('firebase_public_keys', 3600, function () {
            $response = Http::get($this->jwksUrl);

            if (!$response->successful()) {
                throw new Exception('Failed to fetch Firebase public keys');
            }

            $certs = $response->json();
            $keys = [];

            foreach ($certs as $kid => $cert) {
                $keys[$kid] = new Key($cert, 'RS256');
            }

            return $keys;
        });
    }

    /**
     * Verify Firebase-specific JWT claims
     */
    private function verifyFirebaseClaims(array $claims): bool
    {
        // Verify audience (project ID)
        if (($claims['aud'] ?? '') !== $this->projectId) {
            \Log::warning('Firebase token audience mismatch', [
                'expected' => $this->projectId,
                'actual' => $claims['aud'] ?? 'missing'
            ]);
            return false;
        }

        // Verify issuer
        $expectedIssuer = "https://securetoken.google.com/{$this->projectId}";
        if (($claims['iss'] ?? '') !== $expectedIssuer) {
            \Log::warning('Firebase token issuer mismatch', [
                'expected' => $expectedIssuer,
                'actual' => $claims['iss'] ?? 'missing'
            ]);
            return false;
        }

        // Verify subject (user ID) is not empty
        if (empty($claims['sub'] ?? '')) {
            \Log::warning('Firebase token missing subject');
            return false;
        }

        // Verify auth_time is not too old (optional, but recommended)
        $authTime = $claims['auth_time'] ?? 0;
        $maxAge = 24 * 60 * 60; // 24 hours
        if (time() - $authTime > $maxAge) {
            \Log::warning('Firebase token auth_time too old', [
                'auth_time' => $authTime,
                'current_time' => time()
            ]);
            return false;
        }

        return true;
    }

    /**
     * Check if user's email is verified in Firebase claims
     */
    public function isEmailVerified(array $claims): bool
    {
        return $claims['email_verified'] ?? false;
    }

    /**
     * Revoke user's Firebase tokens (logout)
     */
    public function revokeUserTokens(string $firebaseUid): bool
    {
        // In a real implementation, you would call Firebase Admin SDK
        // For now, we'll just clear any cached data related to this user

        // Clear any user-specific caches
        Cache::forget("user_permissions_{$firebaseUid}");
        Cache::forget("user_profile_{$firebaseUid}");

        return true;
    }
}