<template>
  <div>
    <!-- Social Login Buttons -->
    <div class="space-y-3 mb-6">
      <button
        type="button"
        @click="signUpWithGoogle"
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
        @click="signUpWithGithub"
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
        <span class="px-2 text-xs" style="background: rgba(9, 9, 11, 0.8); color: #A1A1AA;">Or continue with email</span>
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

    <!-- Firebase Email/Password Form -->
    <form @submit.prevent="createFirebaseAccount" class="space-y-4">
      <div class="grid grid-cols-2 gap-4">
        <!-- Name -->
        <div class="space-y-2">
          <label for="name" class="block text-sm font-medium" style="color: #FAFAFA;">
            Full Name
          </label>
          <input
            id="name"
            v-model="form.name"
            type="text"
            required
            placeholder="Sarah Johnson"
            class="flex w-full rounded-lg text-base transition-all duration-200 file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-gray-400 focus:outline-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm backdrop-blur-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/50"
            style="background: rgba(9, 9, 11, 0.8); border: 1px solid #27272A; color: #FAFAFA; height: 3.5rem !important; padding: 1rem 1.5rem !important;">
        </div>

        <!-- Role -->
        <div class="space-y-2">
          <label for="role" class="block text-sm font-medium" style="color: #FAFAFA;">
            Role
          </label>
          <select
            id="role"
            v-model="form.role"
            required
            class="flex w-full rounded-lg text-base transition-all duration-200 focus:outline-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm backdrop-blur-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/50"
            style="background: rgba(9, 9, 11, 0.8); border: 1px solid #27272A; color: #FAFAFA; height: 3.5rem !important; padding: 1rem 1.5rem !important;">
            <option value="" style="background: #1F2937; color: #FAFAFA;">Select role</option>
            <option value="Product Manager" style="background: #1F2937; color: #FAFAFA;">Product Manager</option>
            <option value="Senior Product Manager" style="background: #1F2937; color: #FAFAFA;">Senior Product Manager</option>
            <option value="Director of Product" style="background: #1F2937; color: #FAFAFA;">Director of Product</option>
            <option value="VP of Product" style="background: #1F2937; color: #FAFAFA;">VP of Product</option>
            <option value="Product Owner" style="background: #1F2937; color: #FAFAFA;">Product Owner</option>
            <option value="Founder/CEO" style="background: #1F2937; color: #FAFAFA;">Founder/CEO</option>
            <option value="Other" style="background: #1F2937; color: #FAFAFA;">Other</option>
          </select>
        </div>
      </div>

      <!-- Email Address -->
      <div class="space-y-2">
        <label for="email" class="block text-sm font-medium" style="color: #FAFAFA;">
          Work Email
        </label>
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
        <label for="password" class="block text-sm font-medium" style="color: #FAFAFA;">
          Password
        </label>
        <input
          id="password"
          v-model="form.password"
          type="password"
          required
          placeholder="Min. 8 characters"
          class="flex w-full rounded-lg text-base transition-all duration-200 file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-gray-400 focus:outline-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm backdrop-blur-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/50"
          style="background: rgba(9, 9, 11, 0.8); border: 1px solid #27272A; color: #FAFAFA; height: 3.5rem !important; padding: 1rem 1.5rem !important;">
      </div>

      <!-- How did you hear about us -->
      <div class="space-y-2">
        <label for="source" class="block text-sm font-medium" style="color: #FAFAFA;">
          How did you hear about us?
        </label>
        <select
          id="source"
          v-model="form.source"
          class="flex w-full rounded-lg text-base transition-all duration-200 focus:outline-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm backdrop-blur-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/50"
          style="background: rgba(9, 9, 11, 0.8); border: 1px solid #27272A; color: #FAFAFA; height: 3.5rem !important; padding: 1rem 1.5rem !important;">
          <option value="" style="background: #1F2937; color: #FAFAFA;">Select source</option>
          <option value="Google Search" style="background: #1F2937; color: #FAFAFA;">Google Search</option>
          <option value="Social Media (LinkedIn, Twitter)" style="background: #1F2937; color: #FAFAFA;">Social Media (LinkedIn, Twitter)</option>
          <option value="Product Hunt" style="background: #1F2937; color: #FAFAFA;">Product Hunt</option>
          <option value="Colleague/Friend Referral" style="background: #1F2937; color: #FAFAFA;">Colleague/Friend Referral</option>
          <option value="Blog/Article" style="background: #1F2937; color: #FAFAFA;">Blog/Article</option>
          <option value="Conference/Event" style="background: #1F2937; color: #FAFAFA;">Conference/Event</option>
          <option value="Other" style="background: #1F2937; color: #FAFAFA;">Other</option>
        </select>
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
        Start Free Trial
      </button>

      <p class="text-xs text-center" style="color: #A1A1AA;">
        By signing up, you agree to our
        <a href="#" class="underline" style="color: #884DFF;">Terms</a> and
        <a href="#" class="underline" style="color: #884DFF;">Privacy Policy</a>
      </p>
    </form>
  </div>
</template>

<script>
import firebaseAuth from '../../firebase.js';

export default {
  name: 'FirebaseRegisterForm',
  data() {
    return {
      loading: false,
      error: null,
      success: null,
      form: {
        name: '',
        role: '',
        email: '',
        password: '',
        source: ''
      }
    };
  },
  methods: {
    async createFirebaseAccount() {
      this.loading = true;
      this.error = null;
      this.success = null;

      try {
        // Create Firebase account
        const result = await firebaseAuth.createAccount(
          this.form.email,
          this.form.password,
          this.form.name
        );

        if (result.success) {
          // Get the Firebase token
          const token = await result.user.getIdToken();

          if (result.emailVerificationSent) {
            // For email verification users, still create the Laravel profile
            // but they'll be redirected to verify their email
            this.success = 'Account created successfully! Please check your email to verify your account.';

            // Create Laravel profile with unverified status
            await this.createLaravelProfile(token);

            // Redirect to verification page with user's email
            setTimeout(() => {
              window.location.href = `/verify-email?email=${encodeURIComponent(this.form.email)}`;
            }, 2000);
            return;
          } else {
            this.success = 'Account created successfully! Setting up your profile...';

            // Send additional profile data to Laravel backend
            await this.createLaravelProfile(token);
          }

        } else {
          this.error = result.error || 'Failed to create account';
        }
      } catch (error) {
        console.error('Firebase account creation error:', error);
        this.error = 'An unexpected error occurred. Please try again.';
      } finally {
        this.loading = false;
      }
    },

    async createLaravelProfile(firebaseToken) {
      try {
        const response = await fetch('/auth/firebase', {
          method: 'POST',
          credentials: 'include', // Include cookies for session
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'Authorization': `Bearer ${firebaseToken}`,
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
          },
          body: JSON.stringify({
            token: firebaseToken,
            profile_data: {
              title: this.form.role,
              source: this.form.source
            }
          })
        });

        const result = await response.json();

        if (response.ok) {
          // For verified users (social login), redirect to dashboard
          if (result.user && result.user.email_verified_at) {
            this.success = 'Account created successfully! Redirecting to dashboard...';
            setTimeout(() => {
              window.location.href = '/dashboard';
            }, 1500);
          }
          // For unverified users, the redirect to verification page happens in main flow
        } else {
          console.error('Laravel profile creation failed:', result);
          // Only show error if this isn't part of email verification flow
          if (!this.success.includes('check your email')) {
            this.error = 'Account created but profile setup failed. You can complete your profile after email verification.';
          }
        }
      } catch (error) {
        console.error('Laravel profile creation error:', error);
        // Only show error if this isn't part of email verification flow
        if (!this.success.includes('check your email')) {
          this.error = 'Account created but profile setup failed. You can complete your profile after email verification.';
        }
      }
    },

    async signUpWithGoogle() {
      this.loading = true;
      this.error = null;
      this.success = null;

      try {
        const result = await firebaseAuth.signInWithGoogle();

        if (result.success) {
          this.success = 'Successfully signed up with Google! Redirecting...';
          setTimeout(() => {
            window.location.href = '/dashboard';
          }, 1500);
        } else {
          this.error = result.error || 'Failed to sign up with Google';
        }
      } catch (error) {
        console.error('Google signup error:', error);
        this.error = 'An unexpected error occurred. Please try again.';
      } finally {
        this.loading = false;
      }
    },

    async signUpWithGithub() {
      this.loading = true;
      this.error = null;
      this.success = null;

      try {
        const result = await firebaseAuth.signInWithGithub();

        if (result.success) {
          this.success = 'Successfully signed up with GitHub! Redirecting...';
          setTimeout(() => {
            window.location.href = '/dashboard';
          }, 1500);
        } else {
          this.error = result.error || 'Failed to sign up with GitHub';
        }
      } catch (error) {
        console.error('GitHub signup error:', error);
        this.error = 'An unexpected error occurred. Please try again.';
      } finally {
        this.loading = false;
      }
    }
  }
};
</script>