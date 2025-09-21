@extends('layouts.auth')

@section('content')
<div class="w-full max-w-md">
    <!-- Header -->
    <div class="text-center" style="margin-bottom: 3rem !important;">
        <a href="/" class="inline-flex items-center gap-2 text-2xl font-bold mb-6" style="color: #884DFF;">
            <img src="/logo.svg" alt="ReleaseIt.ai" class="h-8 w-auto">        </a>
        <div class="space-y-2">
            <h1 class="text-3xl font-bold" style="color: #FAFAFA;">Welcome Back</h1>
            <p style="color: #A1A1AA;">
                Continue your journey to organized product management
            </p>
        </div>
    </div>

    <!-- Welcome Back Message -->
    <div class="p-4 border rounded-lg" style="margin-bottom: 2rem !important; background: rgba(136, 77, 255, 0.05); border-color: rgba(136, 77, 255, 0.1);">
        <div class="flex items-center gap-3">
            <svg class="h-5 w-5 flex-shrink-0" style="color: #884DFF;" fill="currentColor" viewBox="0 0 24 24">
                <path fill-rule="evenodd" d="M9 3.75H6.912a2.25 2.25 0 00-2.15 1.588L2.35 13.177a68.394 68.394 0 00-.312 2.953 4.5 4.5 0 00.928 2.902l.34.571A4.5 4.5 0 006.912 21h10.176a4.5 4.5 0 003.606-1.797l.34-.571a4.5 4.5 0 00.928-2.902 68.325 68.325 0 00-.312-2.953L19.238 5.338A2.25 2.25 0 0017.088 3.75H15M9 3.75v1.5a.75.75 0 01-1.5 0v-1.5M9 3.75h6m0 0v1.5a.75.75 0 01-1.5 0v-1.5m1.5 0h.75a.75.75 0 01.75.75v.75a.75.75 0 01-.75.75h-6a.75.75 0 01-.75-.75v-.75a.75.75 0 01.75-.75H15z" clip-rule="evenodd" />
            </svg>
            <p class="text-sm" style="color: #A1A1AA;">
                <span class="font-medium" style="color: #884DFF;">Back to focus mode!</span> Choose your preferred sign-in method below.
            </p>
        </div>
    </div>

    <div class="dashboard-card border-2 shadow-xl p-6">
        <!-- Card Header -->
        <div class="text-center pb-6 mb-6">
            <h2 class="text-xl font-semibold mb-2" style="color: #FAFAFA;">Sign In</h2>
            <p class="text-sm" style="color: #A1A1AA;">
                Welcome back! Choose how you'd like to sign in.
            </p>
        </div>

        <!-- Social Login Buttons -->
        <div class="space-y-3 mb-6">
            <button type="button" class="w-full flex items-center justify-start border border-gray-600 rounded-lg hover:bg-gray-800 transition-all duration-200" style="background: rgba(9, 9, 11, 0.8); color: #FAFAFA; height: 3.5rem !important; padding: 1rem 1.5rem !important;">
                <svg class="w-5 h-5 mr-3" viewBox="0 0 24 24">
                    <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                    <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                    <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
                    <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
                </svg>
                Continue with Google
            </button>

            <button type="button" class="w-full flex items-center justify-start border border-gray-600 rounded-lg hover:bg-gray-800 transition-all duration-200" style="background: rgba(9, 9, 11, 0.8); color: #FAFAFA; height: 3.5rem !important; padding: 1rem 1.5rem !important;">
                <svg class="w-5 h-5 mr-3" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M12 0c-6.626 0-12 5.373-12 12 0 5.302 3.438 9.8 8.207 11.387.599.111.793-.261.793-.577v-2.234c-3.338.726-4.033-1.416-4.033-1.416-.546-1.387-1.333-1.756-1.333-1.756-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23.957-.266 1.983-.399 3.003-.404 1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222v3.293c0 .319.192.694.801.576 4.765-1.589 8.199-6.086 8.199-11.386 0-6.627-5.373-12-12-12z"/>
                </svg>
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

        <!-- Login Method Toggle -->
        <div class="flex rounded-lg p-1 mb-6" style="background: #27272A;">
            <button type="button" id="magic-tab" onclick="switchToMagic()" class="flex-1 rounded-md px-3 py-2 text-sm font-medium transition-colors" style="background: rgba(9, 9, 11, 0.8); color: #FAFAFA; box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);">
                Magic Link
            </button>
            <button type="button" id="password-tab" onclick="switchToPassword()" class="flex-1 rounded-md px-3 py-2 text-sm font-medium transition-colors" style="color: #A1A1AA;">
                Password
            </button>
        </div>

        <form method="POST" action="{{ route('login') }}" class="space-y-4">
            @csrf

            <!-- Email Address -->
            <div class="space-y-2">
                <label for="email" class="block text-sm font-medium" style="color: #FAFAFA;">Email</label>
                <input id="email" name="email" type="email" autocomplete="email" required
                    value="{{ old('email') }}"
                    placeholder="sarah@company.com"
                    class="flex w-full rounded-lg text-base transition-all duration-200 file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-gray-400 focus:outline-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm backdrop-blur-sm @error('email') border-red-500 focus:border-red-500 focus:ring-2 focus:ring-red-500/50 @else focus:border-purple-500 focus:ring-2 focus:ring-purple-500/50 @enderror"
                    style="background: rgba(9, 9, 11, 0.8); border: 1px solid #27272A; color: #FAFAFA; height: 3.5rem !important; padding: 1rem 1.5rem !important;">
                @error('email')
                    <p class="text-sm" style="color: #EF4444;">{{ $message }}</p>
                @enderror
            </div>

            <!-- Password -->
            <div id="password-section" class="space-y-2" style="display: none;">
                <div class="flex items-center justify-between">
                    <label for="password" class="block text-sm font-medium" style="color: #FAFAFA;">Password</label>
                    <button type="button" onclick="switchToMagic()" class="text-sm transition-colors duration-200" style="color: #884DFF;">
                        Forgot? Use magic link
                    </button>
                </div>
                <div class="relative">
                    <input id="password" name="password" type="password" autocomplete="current-password"
                        placeholder="Enter your password"
                        class="flex w-full rounded-lg text-base transition-all duration-200 file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-gray-400 focus:outline-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm backdrop-blur-sm @error('password') border-red-500 focus:border-red-500 focus:ring-2 focus:ring-red-500/50 @else focus:border-purple-500 focus:ring-2 focus:ring-purple-500/50 @enderror"
                        style="background: rgba(9, 9, 11, 0.8); border: 1px solid #27272A; color: #FAFAFA; height: 3.5rem !important; padding: 1rem 1.5rem !important; padding-right: 3rem !important;">
                    <button type="button" onclick="togglePassword()" class="absolute right-0 top-0 flex items-center justify-center transition-colors duration-200" style="height: 3.5rem; width: 3rem; color: #A1A1AA;">
                        <svg id="eye-open" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                        </svg>
                        <svg id="eye-closed" class="h-4 w-4 hidden" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.878 9.878L8.464 8.464M14.12 14.12l1.415 1.415M14.12 14.12L9.878 9.878" />
                        </svg>
                    </button>
                </div>
                @error('password')
                    <p class="text-sm" style="color: #EF4444;">{{ $message }}</p>
                @enderror
            </div>

            <!-- Magic Link Info -->
            <div id="magic-info" class="p-3 border rounded-lg" style="background: rgba(136, 77, 255, 0.05); border-color: rgba(136, 77, 255, 0.1);">
                <p class="text-sm" style="color: #A1A1AA;">
                    <span class="font-medium" style="color: #884DFF;">No password needed!</span> We'll send you a secure link to sign in instantly.
                </p>
            </div>

            <!-- Submit Button -->
            <button type="submit" id="submit-btn" class="w-full text-lg flex items-center justify-center gap-2 purple-gradient-button rounded-lg font-medium text-white transition-all duration-200 transform hover:scale-[1.02] focus:outline-none focus:ring-2 focus:ring-purple-500 focus:ring-offset-2 focus:ring-offset-gray-900" style="height: 3.5rem !important; padding: 1rem 1.5rem !important;">
                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                    <path fill-rule="evenodd" d="M10.5 3.75a6 6 0 00-5.98 6.496A5.25 5.25 0 006.75 20.25H16.5a4.5 4.5 0 003.256-7.606 3 3 0 00-1.787-5.392A6.016 6.016 0 0010.5 3.75zm2.03 5.47a.75.75 0 00-1.06 0l-3 3a.75.75 0 101.06 1.06l1.72-1.72v4.94a.75.75 0 001.5 0v-4.94l1.72 1.72a.75.75 0 101.06-1.06l-3-3z" clip-rule="evenodd" />
                </svg>
                <span id="submit-text">Send Magic Link</span>
            </button>

            <p class="text-xs text-center" style="color: #A1A1AA;">
                Secure, encrypted, and ADHD-friendly authentication
            </p>
        </form>
    </div>

    <div class="text-center mt-6">
        <p class="text-sm" style="color: #A1A1AA;">
            Don't have an account?
            <a href="{{ route('register') }}" class="font-medium hover:underline" style="color: #884DFF;">
                Start your free trial
            </a>
        </p>
    </div>

    <!-- Additional Help -->
    <div class="text-center mt-4">
        <p class="text-xs" style="color: #A1A1AA;">
            Need help? Contact
            <a href="mailto:support@releaseit.ai" class="hover:underline" style="color: #884DFF;">
                support@releaseit.ai
            </a>
        </p>
    </div>
</div>

<script>
function switchToMagic() {
    // Update tabs
    const magicTab = document.getElementById('magic-tab');
    const passwordTab = document.getElementById('password-tab');

    magicTab.style.background = 'rgba(9, 9, 11, 0.8)';
    magicTab.style.color = '#FAFAFA';
    magicTab.style.boxShadow = '0 1px 3px rgba(0, 0, 0, 0.1)';

    passwordTab.style.background = 'transparent';
    passwordTab.style.color = '#A1A1AA';
    passwordTab.style.boxShadow = 'none';

    // Show/hide sections
    document.getElementById('password-section').style.display = 'none';
    document.getElementById('magic-info').style.display = 'block';

    // Update button
    document.getElementById('submit-text').textContent = 'Send Magic Link';
    document.getElementById('password').removeAttribute('required');
}

function switchToPassword() {
    // Update tabs
    const magicTab = document.getElementById('magic-tab');
    const passwordTab = document.getElementById('password-tab');

    magicTab.style.background = 'transparent';
    magicTab.style.color = '#A1A1AA';
    magicTab.style.boxShadow = 'none';

    passwordTab.style.background = 'rgba(9, 9, 11, 0.8)';
    passwordTab.style.color = '#FAFAFA';
    passwordTab.style.boxShadow = '0 1px 3px rgba(0, 0, 0, 0.1)';

    // Show/hide sections
    document.getElementById('password-section').style.display = 'block';
    document.getElementById('magic-info').style.display = 'none';

    // Update button
    document.getElementById('submit-text').textContent = 'Sign In';
    document.getElementById('password').setAttribute('required', 'required');
}

function togglePassword() {
    const passwordInput = document.getElementById('password');
    const eyeOpen = document.getElementById('eye-open');
    const eyeClosed = document.getElementById('eye-closed');

    if (passwordInput.type === 'password') {
        passwordInput.type = 'text';
        eyeOpen.classList.add('hidden');
        eyeClosed.classList.remove('hidden');
    } else {
        passwordInput.type = 'password';
        eyeOpen.classList.remove('hidden');
        eyeClosed.classList.add('hidden');
    }
}
</script>
@endsection