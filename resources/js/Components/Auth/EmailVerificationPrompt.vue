<template>
  <div class="w-full max-w-md mx-auto">
    <!-- Header -->
    <div class="text-center mb-8">
      <div class="flex justify-center mb-4">
        <div class="w-16 h-16 rounded-full flex items-center justify-center" style="background: rgba(136, 77, 255, 0.1);">
          <svg class="w-8 h-8" style="color: #884DFF;" fill="currentColor" viewBox="0 0 24 24">
            <path d="M1.5 8.67v8.58a3 3 0 003 3h15a3 3 0 003-3V8.67l-8.928 5.493a3 3 0 01-3.144 0L1.5 8.67z" />
            <path d="M22.5 6.908V6.75a3 3 0 00-3-3h-15a3 3 0 00-3 3v.158l9.714 5.978a1.5 1.5 0 001.572 0L22.5 6.908z" />
          </svg>
        </div>
      </div>
      <h1 class="text-2xl font-bold mb-2" style="color: #FAFAFA;">Check Your Email</h1>
      <p class="text-sm" style="color: #A1A1AA;">
        We've sent a verification link to <strong style="color: #884DFF;">{{ userEmail }}</strong>
      </p>
    </div>

    <div class="dashboard-card border-2 shadow-xl p-6">
      <!-- Verification Instructions -->
      <div class="text-center mb-6">
        <h2 class="text-lg font-semibold mb-3" style="color: #FAFAFA;">Verify Your Email Address</h2>
        <p class="text-sm mb-4" style="color: #A1A1AA;">
          To complete your registration and access your dashboard, please click the verification link in your email.
        </p>

        <!-- Steps -->
        <div class="text-left space-y-3 mb-6">
          <div class="flex items-start gap-3">
            <div class="w-6 h-6 rounded-full flex items-center justify-center flex-shrink-0 mt-0.5" style="background: rgba(136, 77, 255, 0.2);">
              <span class="text-xs font-medium" style="color: #884DFF;">1</span>
            </div>
            <p class="text-sm" style="color: #A1A1AA;">Check your inbox (and spam folder) for an email from ReleaseIt.ai</p>
          </div>
          <div class="flex items-start gap-3">
            <div class="w-6 h-6 rounded-full flex items-center justify-center flex-shrink-0 mt-0.5" style="background: rgba(136, 77, 255, 0.2);">
              <span class="text-xs font-medium" style="color: #884DFF;">2</span>
            </div>
            <p class="text-sm" style="color: #A1A1AA;">Click the verification link in the email</p>
          </div>
          <div class="flex items-start gap-3">
            <div class="w-6 h-6 rounded-full flex items-center justify-center flex-shrink-0 mt-0.5" style="background: rgba(136, 77, 255, 0.2);">
              <span class="text-xs font-medium" style="color: #884DFF;">3</span>
            </div>
            <p class="text-sm" style="color: #A1A1AA;">You'll be automatically redirected to your dashboard</p>
          </div>
        </div>
      </div>

      <!-- Error Message -->
      <div v-if="error" class="mb-4 p-3 border rounded-lg" style="background: rgba(239, 68, 68, 0.1); border-color: rgba(239, 68, 68, 0.2);">
        <p class="text-sm" style="color: #EF4444;">{{ error }}</p>
      </div>

      <!-- Success Message -->
      <div v-if="success" class="mb-4 p-3 border rounded-lg" style="background: rgba(34, 197, 94, 0.1); border-color: rgba(34, 197, 94, 0.2);">
        <p class="text-sm" style="color: #22C55E;">{{ success }}</p>
      </div>

      <!-- Resend Email Button -->
      <div class="space-y-4">
        <button
          @click="resendVerification"
          :disabled="loading || cooldownRemaining > 0"
          class="w-full flex items-center justify-center gap-2 border border-gray-600 rounded-lg hover:bg-gray-800 transition-all duration-200 disabled:opacity-50 disabled:cursor-not-allowed"
          style="background: rgba(9, 9, 11, 0.8); color: #FAFAFA; height: 3.5rem !important; padding: 1rem 1.5rem !important;"
        >
          <svg v-if="!loading" class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
            <path d="M21.731 2.269a2.625 2.625 0 00-3.712 0l-1.157 1.157 3.712 3.712 1.157-1.157a2.625 2.625 0 000-3.712zM19.513 8.199l-3.712-3.712-8.4 8.4a5.25 5.25 0 00-1.32 2.214l-.8 2.685a.75.75 0 00.933.933l2.685-.8a5.25 5.25 0 002.214-1.32l8.4-8.4z" />
            <path d="M5.25 5.25a3 3 0 00-3 3v10.5a3 3 0 003 3h10.5a3 3 0 003-3V13.5a.75.75 0 00-1.5 0v5.25a1.5 1.5 0 01-1.5 1.5H5.25a1.5 1.5 0 01-1.5-1.5V8.25a1.5 1.5 0 011.5-1.5h5.25a.75.75 0 000-1.5H5.25z" />
          </svg>
          <div v-else class="w-5 h-5 animate-spin rounded-full border-2 border-gray-300 border-t-purple-500"></div>
          {{ loading ? 'Sending...' : cooldownRemaining > 0 ? `Resend in ${cooldownRemaining}s` : 'Resend Verification Email' }}
        </button>

        <!-- Check Again Button -->
        <button
          @click="checkVerificationStatus"
          :disabled="loading"
          class="w-full purple-gradient-button rounded-lg font-medium text-white transition-all duration-200 transform hover:scale-[1.02] focus:outline-none focus:ring-2 focus:ring-purple-500 focus:ring-offset-2 focus:ring-offset-gray-900 disabled:opacity-50 disabled:cursor-not-allowed disabled:transform-none"
          style="height: 3.5rem !important; padding: 1rem 1.5rem !important;"
        >
          <span v-if="!checking">I've Verified My Email</span>
          <div v-else class="flex items-center justify-center gap-2">
            <div class="w-5 h-5 animate-spin rounded-full border-2 border-gray-300 border-t-white"></div>
            Checking...
          </div>
        </button>
      </div>

      <!-- Help Text -->
      <div class="text-center mt-6 pt-6 border-t" style="border-color: #27272A;">
        <p class="text-xs mb-2" style="color: #A1A1AA;">
          Didn't receive the email? Check your spam folder or try a different email address.
        </p>
        <p class="text-xs" style="color: #A1A1AA;">
          Need help? Contact
          <a href="mailto:support@releaseit.ai" class="hover:underline" style="color: #884DFF;">
            support@releaseit.ai
          </a>
        </p>
      </div>
    </div>

    <!-- Back to Login -->
    <div class="text-center mt-6">
      <p class="text-sm" style="color: #A1A1AA;">
        Want to try a different email?
        <a href="/register" class="font-medium hover:underline" style="color: #884DFF;">
          Create new account
        </a>
      </p>
    </div>
  </div>
</template>

<script>
import firebaseAuth from '../../firebase.js';

export default {
  name: 'EmailVerificationPrompt',
  props: {
    userEmail: {
      type: String,
      default: ''
    }
  },
  data() {
    return {
      loading: false,
      checking: false,
      error: null,
      success: null,
      cooldownRemaining: 0,
      cooldownTimer: null
    };
  },
  mounted() {
    // Start checking verification status periodically
    this.startVerificationPolling();
  },
  beforeUnmount() {
    if (this.cooldownTimer) {
      clearInterval(this.cooldownTimer);
    }
    if (this.verificationPoller) {
      clearInterval(this.verificationPoller);
    }
  },
  methods: {
    async resendVerification() {
      this.loading = true;
      this.error = null;
      this.success = null;

      try {
        const result = await firebaseAuth.resendEmailVerification();

        if (result.success) {
          this.success = 'Verification email sent! Please check your inbox.';
          this.startCooldown();
        } else {
          this.error = result.error || 'Failed to send verification email';
        }
      } catch (error) {
        console.error('Resend verification error:', error);
        this.error = 'An unexpected error occurred. Please try again.';
      } finally {
        this.loading = false;
      }
    },

    async checkVerificationStatus() {
      this.checking = true;
      this.error = null;

      try {
        // Reload the user to get fresh token with updated email verification status
        await firebaseAuth.auth.currentUser?.reload();

        if (firebaseAuth.isEmailVerified()) {
          this.success = 'Email verified! Redirecting to dashboard...';
          setTimeout(() => {
            window.location.href = '/dashboard';
          }, 1500);
        } else {
          this.error = 'Email not yet verified. Please check your email and click the verification link.';
        }
      } catch (error) {
        console.error('Verification check error:', error);
        this.error = 'Unable to check verification status. Please try again.';
      } finally {
        this.checking = false;
      }
    },

    startCooldown() {
      this.cooldownRemaining = 60; // 60 seconds cooldown
      this.cooldownTimer = setInterval(() => {
        this.cooldownRemaining--;
        if (this.cooldownRemaining <= 0) {
          clearInterval(this.cooldownTimer);
        }
      }, 1000);
    },

    startVerificationPolling() {
      // Check verification status every 5 seconds
      this.verificationPoller = setInterval(async () => {
        try {
          await firebaseAuth.auth.currentUser?.reload();
          if (firebaseAuth.isEmailVerified()) {
            clearInterval(this.verificationPoller);
            this.success = 'Email verified! Redirecting to dashboard...';
            setTimeout(() => {
              window.location.href = '/dashboard';
            }, 1500);
          }
        } catch (error) {
          // Silently handle polling errors
          console.warn('Verification polling error:', error);
        }
      }, 5000);
    }
  }
};
</script>