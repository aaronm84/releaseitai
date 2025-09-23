@extends('layouts.auth')

@section('content')
<div class="w-full max-w-md mx-auto">
    <!-- Header -->
    <div class="text-center mb-8">
        <div class="flex justify-center mb-4">
            <div class="w-16 h-16 rounded-full flex items-center justify-center" style="background: rgba(136, 77, 255, 0.1);">
                <svg class="w-8 h-8" style="color: #884DFF;" fill="currentColor" viewBox="0 0 24 24">
                    <path fill-rule="evenodd" d="M2.25 12c0-5.385 4.365-9.75 9.75-9.75s9.75 4.365 9.75 9.75-4.365 9.75-9.75 9.75S2.25 17.385 2.25 12zm13.36-1.814a.75.75 0 10-1.22-.872l-3.236 4.53L9.53 12.22a.75.75 0 00-1.06 1.06l2.25 2.25a.75.75 0 001.14-.094l3.75-5.25z" clip-rule="evenodd" />
                </svg>
            </div>
        </div>
        <h1 class="text-2xl font-bold mb-2" style="color: #FAFAFA;">Email Verification</h1>
        <p class="text-sm" style="color: #A1A1AA;">
            Completing your email verification...
        </p>
    </div>

    <div class="dashboard-card border-2 shadow-xl p-6">
        <div class="text-center">
            <div id="verification-status" class="mb-6">
                <div class="w-8 h-8 mx-auto mb-4 animate-spin rounded-full border-2 border-gray-300 border-t-purple-500"></div>
                <p class="text-sm" style="color: #A1A1AA;">Verifying your email address...</p>
            </div>

            <div id="verification-success" class="hidden mb-6">
                <div class="w-16 h-16 mx-auto mb-4 rounded-full flex items-center justify-center" style="background: rgba(34, 197, 94, 0.1);">
                    <svg class="w-8 h-8" style="color: #22C55E;" fill="currentColor" viewBox="0 0 24 24">
                        <path fill-rule="evenodd" d="M2.25 12c0-5.385 4.365-9.75 9.75-9.75s9.75 4.365 9.75 9.75-4.365 9.75-9.75 9.75S2.25 17.385 2.25 12zm13.36-1.814a.75.75 0 10-1.22-.872l-3.236 4.53L9.53 12.22a.75.75 0 00-1.06 1.06l2.25 2.25a.75.75 0 001.14-.094l3.75-5.25z" clip-rule="evenodd" />
                    </svg>
                </div>
                <h2 class="text-lg font-semibold mb-2" style="color: #22C55E;">Email Verified!</h2>
                <p class="text-sm mb-4" style="color: #A1A1AA;">Your email has been successfully verified. Redirecting to dashboard...</p>
            </div>

            <div id="verification-error" class="hidden mb-6">
                <div class="w-16 h-16 mx-auto mb-4 rounded-full flex items-center justify-center" style="background: rgba(239, 68, 68, 0.1);">
                    <svg class="w-8 h-8" style="color: #EF4444;" fill="currentColor" viewBox="0 0 24 24">
                        <path fill-rule="evenodd" d="M12 2.25c-5.385 0-9.75 4.365-9.75 9.75s4.365 9.75 9.75 9.75 9.75-4.365 9.75-9.75S17.385 2.25 12 2.25zm-1.72 6.97a.75.75 0 10-1.06 1.06L10.94 12l-1.72 1.72a.75.75 0 101.06 1.06L12 13.06l1.72 1.72a.75.75 0 101.06-1.06L13.06 12l1.72-1.72a.75.75 0 10-1.06-1.06L12 10.94l-1.72-1.72z" clip-rule="evenodd" />
                    </svg>
                </div>
                <h2 class="text-lg font-semibold mb-2" style="color: #EF4444;">Verification Failed</h2>
                <p class="text-sm mb-4" style="color: #A1A1AA;" id="error-message">Something went wrong during email verification.</p>
            </div>

            <div id="manual-continue" class="hidden">
                <a href="/dashboard"
                   class="w-full inline-flex items-center justify-center purple-gradient-button rounded-lg font-medium text-white transition-all duration-200 transform hover:scale-[1.02] focus:outline-none focus:ring-2 focus:ring-purple-500 focus:ring-offset-2 focus:ring-offset-gray-900"
                   style="height: 3.5rem !important; padding: 1rem 1.5rem !important;">
                    Continue to Dashboard
                </a>
            </div>
        </div>
    </div>

    <!-- Back to Login -->
    <div class="text-center mt-6">
        <p class="text-sm" style="color: #A1A1AA;">
            <a href="/login" class="font-medium hover:underline" style="color: #884DFF;">
                Back to Login
            </a>
        </p>
    </div>
</div>

<script type="module">
import firebaseAuth from '/resources/js/firebase.js';

document.addEventListener('DOMContentLoaded', async function() {
    const verificationStatus = document.getElementById('verification-status');
    const verificationSuccess = document.getElementById('verification-success');
    const verificationError = document.getElementById('verification-error');
    const errorMessage = document.getElementById('error-message');
    const manualContinue = document.getElementById('manual-continue');

    try {
        // Check if this is an email verification callback
        const urlParams = new URLSearchParams(window.location.search);

        // Apply the action code (verify email)
        if (window.location.href.includes('mode=verifyEmail')) {
            // Firebase will handle the verification automatically when user clicks the link
            // We just need to check if they're now verified

            // Wait a moment for Firebase to process
            setTimeout(async () => {
                try {
                    // Check if user is signed in and verified
                    const user = firebaseAuth.getCurrentUser();
                    if (user) {
                        await user.reload(); // Refresh user data
                        if (user.emailVerified) {
                            // Success!
                            verificationStatus.classList.add('hidden');
                            verificationSuccess.classList.remove('hidden');

                            setTimeout(() => {
                                window.location.href = '/dashboard';
                            }, 2000);
                        } else {
                            // Still not verified
                            throw new Error('Email verification was not completed successfully.');
                        }
                    } else {
                        // User not signed in, show manual continue
                        verificationStatus.classList.add('hidden');
                        verificationSuccess.classList.remove('hidden');
                        manualContinue.classList.remove('hidden');
                    }
                } catch (error) {
                    console.error('Verification check error:', error);
                    verificationStatus.classList.add('hidden');
                    verificationError.classList.remove('hidden');
                    errorMessage.textContent = error.message || 'Email verification failed.';
                }
            }, 2000);
        } else {
            // Not a verification callback
            verificationStatus.classList.add('hidden');
            verificationError.classList.remove('hidden');
            errorMessage.textContent = 'Invalid verification link.';
        }
    } catch (error) {
        console.error('Email verification error:', error);
        verificationStatus.classList.add('hidden');
        verificationError.classList.remove('hidden');
        errorMessage.textContent = error.message || 'Email verification failed.';
    }
});
</script>
@endsection