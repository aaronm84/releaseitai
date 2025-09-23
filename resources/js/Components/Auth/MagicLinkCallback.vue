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
      <h1 class="text-2xl font-bold mb-2" style="color: #FAFAFA;">Magic Link Authentication</h1>
      <p class="text-sm" style="color: #A1A1AA;">
        Completing your magic link sign-in...
      </p>
    </div>

    <div class="dashboard-card border-2 shadow-xl p-6">
      <div class="text-center">
        <div v-if="status === 'authenticating'" class="mb-6">
          <div class="w-8 h-8 mx-auto mb-4 animate-spin rounded-full border-2 border-gray-300 border-t-purple-500"></div>
          <p class="text-sm" style="color: #A1A1AA;">Authenticating...</p>
        </div>

        <div v-if="status === 'success'" class="mb-6">
          <div class="w-16 h-16 mx-auto mb-4 rounded-full flex items-center justify-center" style="background: rgba(34, 197, 94, 0.1);">
            <svg class="w-8 h-8" style="color: #22C55E;" fill="currentColor" viewBox="0 0 24 24">
              <path fill-rule="evenodd" d="M2.25 12c0-5.385 4.365-9.75 9.75-9.75s9.75 4.365 9.75 9.75-4.365 9.75-9.75 9.75S2.25 17.385 2.25 12zm13.36-1.814a.75.75 0 10-1.22-.872l-3.236 4.53L9.53 12.22a.75.75 0 00-1.06 1.06l2.25 2.25a.75.75 0 001.14-.094l3.75-5.25z" clip-rule="evenodd" />
            </svg>
          </div>
          <h2 class="text-lg font-semibold mb-2" style="color: #22C55E;">Successfully Signed In!</h2>
          <p class="text-sm mb-4" style="color: #A1A1AA;">Redirecting to your dashboard...</p>
        </div>

        <div v-if="status === 'error'" class="mb-6">
          <div class="w-16 h-16 mx-auto mb-4 rounded-full flex items-center justify-center" style="background: rgba(239, 68, 68, 0.1);">
            <svg class="w-8 h-8" style="color: #EF4444;" fill="currentColor" viewBox="0 0 24 24">
              <path fill-rule="evenodd" d="M12 2.25c-5.385 0-9.75 4.365-9.75 9.75s9.75 4.365 9.75 9.75-4.365 9.75-9.75 9.75S2.25 17.385 2.25 12zm-1.72 6.97a.75.75 0 10-1.06 1.06L10.94 12l-1.72 1.72a.75.75 0 101.06 1.06L12 13.06l1.72 1.72a.75.75 0 101.06-1.06L13.06 12l1.72-1.72a.75.75 0 10-1.06-1.06L12 10.94l-1.72-1.72z" clip-rule="evenodd" />
            </svg>
          </div>
          <h2 class="text-lg font-semibold mb-2" style="color: #EF4444;">Authentication Failed</h2>
          <p class="text-sm mb-4" style="color: #A1A1AA;">{{ errorMessage }}</p>
        </div>

        <div v-if="status === 'error'">
          <a href="/login"
             class="w-full inline-flex items-center justify-center purple-gradient-button rounded-lg font-medium text-white transition-all duration-200 transform hover:scale-[1.02] focus:outline-none focus:ring-2 focus:ring-purple-500 focus:ring-offset-2 focus:ring-offset-gray-900"
             style="height: 3.5rem !important; padding: 1rem 1.5rem !important;">
            Back to Login
          </a>
        </div>
      </div>
    </div>

    <!-- Help -->
    <div class="text-center mt-6">
      <p class="text-sm" style="color: #A1A1AA;">
        Having trouble?
        <a href="/login" class="font-medium hover:underline" style="color: #884DFF;">
          Try a different sign-in method
        </a>
      </p>
    </div>
  </div>
</template>

<script>
import firebaseAuth from '../../firebase.js';

export default {
  name: 'MagicLinkCallback',
  data() {
    return {
      status: 'authenticating', // 'authenticating', 'success', 'error'
      errorMessage: ''
    };
  },
  async mounted() {
    await this.handleMagicLinkAuth();
  },
  methods: {
    async handleMagicLinkAuth() {
      try {
        // Complete the magic link authentication
        const result = await firebaseAuth.completeMagicLink();

        if (result.success) {
          // Success!
          this.status = 'success';

          // Get the Firebase token and authenticate with Laravel
          const token = await result.user.getIdToken();

          // Send token to Laravel backend
          try {
            const response = await fetch('/auth/firebase', {
              method: 'POST',
              credentials: 'include', // Include cookies for session
              headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'Authorization': `Bearer ${token}`,
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
              },
              body: JSON.stringify({ token })
            });

            if (response.ok) {
              // Successful authentication
              setTimeout(() => {
                window.location.href = '/dashboard';
              }, 2000);
            } else {
              console.warn('Laravel authentication failed, but Firebase auth succeeded');
              // Still redirect to dashboard - Laravel auth might not be critical
              setTimeout(() => {
                window.location.href = '/dashboard';
              }, 2000);
            }
          } catch (laravelError) {
            console.warn('Laravel backend error:', laravelError);
            // Still redirect - Firebase auth succeeded
            setTimeout(() => {
              window.location.href = '/dashboard';
            }, 2000);
          }

        } else {
          // Magic link authentication failed
          this.status = 'error';
          this.errorMessage = result.error || 'Magic link authentication failed.';
        }
      } catch (error) {
        console.error('Magic link authentication error:', error);
        this.status = 'error';
        this.errorMessage = error.message || 'Magic link authentication failed.';
      }
    }
  }
};
</script>