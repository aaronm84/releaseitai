<template>
  <div>
    <!-- Social Login Buttons -->
    <div class="space-y-3 mb-6">
      <button
        type="button"
        @click="signInWithGoogle"
        :disabled="loading"
        class="w-full flex items-center justify-start border border-gray-600 rounded-lg hover:bg-gray-800 transition-all duration-200 disabled:opacity-50 disabled:cursor-not-allowed"
        style="background: rgba(9, 9, 11, 0.8); color: #FAFAFA; height: 3.5rem !important; padding: 1rem 1.5rem !important;"
      >
        <svg v-if="!loading" class="w-5 h-5 mr-3" viewBox="0 0 24 24">
          <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
          <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
          <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
          <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
        </svg>
        <div v-else class="w-5 h-5 mr-3 animate-spin rounded-full border-2 border-gray-300 border-t-purple-500"></div>
        Continue with Google
      </button>

      <button
        type="button"
        @click="signInWithGithub"
        :disabled="loading"
        class="w-full flex items-center justify-start border border-gray-600 rounded-lg hover:bg-gray-800 transition-all duration-200 disabled:opacity-50 disabled:cursor-not-allowed"
        style="background: rgba(9, 9, 11, 0.8); color: #FAFAFA; height: 3.5rem !important; padding: 1rem 1.5rem !important;"
      >
        <svg v-if="!loading" class="w-5 h-5 mr-3" fill="currentColor" viewBox="0 0 24 24">
          <path d="M12 0c-6.626 0-12 5.373-12 12 0 5.302 3.438 9.8 8.207 11.387.599.111.793-.261.793-.577v-2.234c-3.338.726-4.033-1.416-4.033-1.416-.546-1.387-1.333-1.756-1.333-1.756-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23.957-.266 1.983-.399 3.003-.404 1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222v3.293c0 .319.192.694.801.576 4.765-1.589 8.199-6.086 8.199-11.386 0-6.627-5.373-12-12-12z"/>
        </svg>
        <div v-else class="w-5 h-5 mr-3 animate-spin rounded-full border-2 border-gray-300 border-t-purple-500"></div>
        Continue with GitHub
      </button>
    </div>

    <!-- Divider -->
    <div class="relative mb-6">
      <div class="absolute inset-0 flex items-center">
        <div class="w-full border-t" style="border-color: #27272A;"></div>
      </div>
      <div class="relative flex justify-center text-xs uppercase">
        <span class="px-2 text-xs" style="background: rgba(9, 9, 11, 0.8); color: #A1A1AA;">Or use email</span>
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

    <!-- Login Method Toggle -->
    <div class="flex rounded-lg p-1 mb-6" style="background: #27272A;">
      <button
        type="button"
        @click="switchToMagic()"
        :class="authMethod === 'magic' ? 'bg-opacity-80 text-white shadow-sm' : 'text-gray-400'"
        class="flex-1 rounded-md px-3 py-2 text-sm font-medium transition-colors"
        style="background: rgba(9, 9, 11, 0.8);">
        Magic Link
      </button>
      <button
        type="button"
        @click="switchToPassword()"
        :class="authMethod === 'password' ? 'bg-opacity-80 text-white shadow-sm' : 'text-gray-400'"
        class="flex-1 rounded-md px-3 py-2 text-sm font-medium transition-colors"
        style="background: rgba(9, 9, 11, 0.8);">
        Password
      </button>
    </div>

    <!-- Email/Password Form -->
    <form v-if="authMethod === 'password'" @submit.prevent="signInWithEmail" class="space-y-4">
      <!-- Email Address -->
      <div class="space-y-2">
        <label for="email" class="block text-sm font-medium" style="color: #FAFAFA;">Email</label>
        <input
          id="email"
          v-model="form.email"
          type="email"
          required
          placeholder="sarah@company.com"
          class="flex w-full rounded-lg text-base transition-all duration-200 file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-gray-400 focus:outline-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm backdrop-blur-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/50"
          style="background: rgba(9, 9, 11, 0.8); border: 1px solid #27272A; color: #FAFAFA; height: 3.5rem !important; padding: 1rem 1.5rem !important;">
      </div>

      <!-- Password -->
      <div class="space-y-2">
        <div class="flex items-center justify-between">
          <label for="password" class="block text-sm font-medium" style="color: #FAFAFA;">Password</label>
          <button type="button" @click="switchToMagic()" class="text-sm transition-colors duration-200" style="color: #884DFF;">
            Forgot? Use magic link
          </button>
        </div>
        <div class="relative">
          <input
            id="password"
            v-model="form.password"
            :type="showPassword ? 'text' : 'password'"
            required
            placeholder="Enter your password"
            class="flex w-full rounded-lg text-base transition-all duration-200 file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-gray-400 focus:outline-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm backdrop-blur-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/50"
            style="background: rgba(9, 9, 11, 0.8); border: 1px solid #27272A; color: #FAFAFA; height: 3.5rem !important; padding: 1rem 1.5rem !important; padding-right: 3rem !important;">
          <button type="button" @click="togglePassword()" class="absolute right-0 top-0 flex items-center justify-center transition-colors duration-200" style="height: 3.5rem; width: 3rem; color: #A1A1AA;">
            <svg v-if="showPassword" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.878 9.878L8.464 8.464M14.12 14.12l1.415 1.415M14.12 14.12L9.878 9.878" />
            </svg>
            <svg v-else class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
            </svg>
          </button>
        </div>
      </div>

      <!-- Submit Button -->
      <button
        type="submit"
        :disabled="loading"
        class="w-full text-lg flex items-center justify-center gap-2 purple-gradient-button rounded-lg font-medium text-white transition-all duration-200 transform hover:scale-[1.02] focus:outline-none focus:ring-2 focus:ring-purple-500 focus:ring-offset-2 focus:ring-offset-gray-900 disabled:opacity-50 disabled:cursor-not-allowed disabled:transform-none"
        style="height: 3.5rem !important; padding: 1rem 1.5rem !important;">
        <svg v-if="!loading" class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
          <path fill-rule="evenodd" d="M7.5 6a4.5 4.5 0 119 0 4.5 4.5 0 01-9 0zM3.751 20.105a8.25 8.25 0 0116.498 0 .75.75 0 01-.437.695A18.683 18.683 0 0112 22.5c-2.786 0-5.433-.608-7.812-1.7a.75.75 0 01-.437-.695z" clip-rule="evenodd" />
        </svg>
        <div v-else class="w-5 h-5 animate-spin rounded-full border-2 border-gray-300 border-t-white"></div>
        Sign In
      </button>
    </form>

    <!-- Magic Link Form -->
    <form v-if="authMethod === 'magic'" @submit.prevent="sendMagicLink" class="space-y-4">
      <!-- Magic Link Info -->
      <div class="p-3 border rounded-lg mb-4" style="background: rgba(136, 77, 255, 0.05); border-color: rgba(136, 77, 255, 0.1);">
        <p class="text-sm" style="color: #A1A1AA;">
          <span class="font-medium" style="color: #884DFF;">No password needed!</span> We'll send you a secure link to sign in instantly.
        </p>
      </div>

      <!-- Email Address -->
      <div class="space-y-2">
        <label for="magic-email" class="block text-sm font-medium" style="color: #FAFAFA;">Email</label>
        <input
          id="magic-email"
          v-model="magicEmail"
          type="email"
          required
          placeholder="sarah@company.com"
          class="flex w-full rounded-lg text-base transition-all duration-200 file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-gray-400 focus:outline-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm backdrop-blur-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/50"
          style="background: rgba(9, 9, 11, 0.8); border: 1px solid #27272A; color: #FAFAFA; height: 3.5rem !important; padding: 1rem 1.5rem !important;">
      </div>

      <!-- Submit Button -->
      <button
        type="submit"
        :disabled="loading"
        class="w-full text-lg flex items-center justify-center gap-2 purple-gradient-button rounded-lg font-medium text-white transition-all duration-200 transform hover:scale-[1.02] focus:outline-none focus:ring-2 focus:ring-purple-500 focus:ring-offset-2 focus:ring-offset-gray-900 disabled:opacity-50 disabled:cursor-not-allowed disabled:transform-none"
        style="height: 3.5rem !important; padding: 1rem 1.5rem !important;">
        <svg v-if="!loading" class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
          <path fill-rule="evenodd" d="M10.5 3.75a6 6 0 00-5.98 6.496A5.25 5.25 0 006.75 20.25H16.5a4.5 4.5 0 003.256-7.606 3 3 0 00-1.787-5.392A6.016 6.016 0 0010.5 3.75zm2.03 5.47a.75.75 0 00-1.06 0l-3 3a.75.75 0 101.06 1.06l1.72-1.72v4.94a.75.75 0 001.5 0v-4.94l1.72 1.72a.75.75 0 101.06-1.06l-3-3z" clip-rule="evenodd" />
        </svg>
        <div v-else class="w-5 h-5 animate-spin rounded-full border-2 border-gray-300 border-t-white"></div>
        Send Magic Link
      </button>

      <p class="text-xs text-center" style="color: #A1A1AA;">
        Secure, encrypted, and ADHD-friendly authentication
      </p>
    </form>
  </div>
</template>

<script>
import firebaseAuth from '../../firebase.js';

export default {
  name: 'FirebaseLoginForm',
  data() {
    return {
      loading: false,
      error: null,
      success: null,
      authMethod: 'magic', // 'magic' or 'password'
      showPassword: false,
      form: {
        email: '',
        password: ''
      },
      magicEmail: ''
    };
  },
  methods: {
    switchToMagic() {
      this.authMethod = 'magic';
      this.error = null;
      this.success = null;
    },

    switchToPassword() {
      this.authMethod = 'password';
      this.error = null;
      this.success = null;
    },

    togglePassword() {
      this.showPassword = !this.showPassword;
    },

    async signInWithEmail() {
      this.loading = true;
      this.error = null;
      this.success = null;

      try {
        const result = await firebaseAuth.signInWithEmail(this.form.email, this.form.password);

        if (result.success) {
          // Check if email is verified
          if (!result.user.emailVerified) {
            this.success = 'Please verify your email before accessing the dashboard. Redirecting...';
            setTimeout(() => {
              window.location.href = `/verify-email?email=${encodeURIComponent(result.user.email)}`;
            }, 1500);
          } else {
            this.success = 'Successfully signed in! Redirecting...';
            setTimeout(() => {
              window.location.href = '/dashboard';
            }, 1500);
          }
        } else {
          this.error = result.error || 'Failed to sign in';
        }
      } catch (error) {
        console.error('Email signin error:', error);
        this.error = 'An unexpected error occurred. Please try again.';
      } finally {
        this.loading = false;
      }
    },

    async sendMagicLink() {
      this.loading = true;
      this.error = null;
      this.success = null;

      try {
        const result = await firebaseAuth.sendMagicLink(this.magicEmail);

        if (result.success) {
          this.success = 'Magic link sent! Check your email and click the link to sign in.';
          this.magicEmail = '';
        } else {
          this.error = result.error || 'Failed to send magic link';
        }
      } catch (error) {
        console.error('Magic link error:', error);
        this.error = 'An unexpected error occurred. Please try again.';
      } finally {
        this.loading = false;
      }
    },

    async signInWithGoogle() {
      this.loading = true;
      this.error = null;
      this.success = null;

      try {
        const result = await firebaseAuth.signInWithGoogle();

        if (result.success) {
          this.success = 'Successfully signed in with Google! Redirecting...';
          setTimeout(() => {
            window.location.href = '/dashboard';
          }, 1500);
        } else {
          this.error = result.error || 'Failed to sign in with Google';
        }
      } catch (error) {
        console.error('Google signin error:', error);
        this.error = 'An unexpected error occurred. Please try again.';
      } finally {
        this.loading = false;
      }
    },

    async signInWithGithub() {
      this.loading = true;
      this.error = null;
      this.success = null;

      try {
        const result = await firebaseAuth.signInWithGithub();

        if (result.success) {
          this.success = 'Successfully signed in with GitHub! Redirecting...';
          setTimeout(() => {
            window.location.href = '/dashboard';
          }, 1500);
        } else {
          this.error = result.error || 'Failed to sign in with GitHub';
        }
      } catch (error) {
        console.error('GitHub signin error:', error);
        this.error = 'An unexpected error occurred. Please try again.';
      } finally {
        this.loading = false;
      }
    }
  }
};
</script>