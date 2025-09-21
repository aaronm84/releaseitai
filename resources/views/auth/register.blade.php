@extends('layouts.auth')

@section('content')
<div class="w-full max-w-md">
    <!-- Header -->
    <div class="text-center mb-8 " style="margin-bottom: 3rem !important;">
        <a href="/" class="inline-flex items-center gap-2 text-2xl font-bold mb-6" style="color: #884DFF;">
            <img src="/logo.svg" alt="ReleaseIt.ai" class="h-8 w-auto">
        </a>
        <div class="space-y-2">
            <h1 class="text-3xl font-bold" style="color: #FAFAFA;">Join 2,000+ ADHD PMs</h1>
            <p style="color: #A1A1AA;">
                Ship products faster with an AI that gets your brain
            </p>
        </div>
    </div>

    <!-- Benefits -->
    <div class="space-y-3" style="margin-bottom: 3rem !important;">
        <div class="flex items-start gap-3">
            <svg class="h-5 w-5 mt-0.5 flex-shrink-0" style="color: #884DFF;" fill="currentColor" viewBox="0 0 24 24">
                <path fill-rule="evenodd" d="M2.25 12c0-5.385 4.365-9.75 9.75-9.75s9.75 4.365 9.75 9.75-4.365 9.75-9.75 9.75S2.25 17.385 2.25 12zm13.36-1.814a.75.75 0 10-1.22-.872l-3.236 4.53L9.53 12.22a.75.75 0 00-1.06 1.06l2.25 2.25a.75.75 0 001.14-.094l3.75-5.25z" clip-rule="evenodd" />
            </svg>
            <p class="text-sm" style="color: #A1A1AA;">Start organizing your product roadmap in under 5 minutes</p>
        </div>
        <div class="flex items-start gap-3">
            <svg class="h-5 w-5 mt-0.5 flex-shrink-0" style="color: #884DFF;" fill="currentColor" viewBox="0 0 24 24">
                <path fill-rule="evenodd" d="M2.25 12c0-5.385 4.365-9.75 9.75-9.75s9.75 4.365 9.75 9.75-4.365 9.75-9.75 9.75S2.25 17.385 2.25 12zm13.36-1.814a.75.75 0 10-1.22-.872l-3.236 4.53L9.53 12.22a.75.75 0 00-1.06 1.06l2.25 2.25a.75.75 0 001.14-.094l3.75-5.25z" clip-rule="evenodd" />
            </svg>
            <p class="text-sm" style="color: #A1A1AA;">AI assistant that understands ADHD brain patterns</p>
        </div>
        <div class="flex items-start gap-3">
            <svg class="h-5 w-5 mt-0.5 flex-shrink-0" style="color: #884DFF;" fill="currentColor" viewBox="0 0 24 24">
                <path fill-rule="evenodd" d="M2.25 12c0-5.385 4.365-9.75 9.75-9.75s9.75 4.365 9.75 9.75-4.365 9.75-9.75 9.75S2.25 17.385 2.25 12zm13.36-1.814a.75.75 0 10-1.22-.872l-3.236 4.53L9.53 12.22a.75.75 0 00-1.06 1.06l2.25 2.25a.75.75 0 001.14-.094l3.75-5.25z" clip-rule="evenodd" />
            </svg>
            <p class="text-sm" style="color: #A1A1AA;">No credit card required - just focus on what matters</p>
        </div>
    </div>

    <div class="dashboard-card border-2 shadow-xl p-6">
        <!-- Card Header -->
        <div class="text-center pb-4 mb-6">
            <div class="flex items-center justify-center">
                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium" style="background: rgba(136, 77, 255, 0.1); color: #884DFF;">
                    <svg class="h-3 w-3 mr-1" fill="currentColor" viewBox="0 0 24 24">
                        <path fill-rule="evenodd" d="M14.615 1.595a.75.75 0 01.359.852L12.982 9.75h7.268a.75.75 0 01.548 1.262l-10.5 11.25a.75.75 0 01-1.272-.71L10.018 14.25H2.75a.75.75 0 01-.548-1.262l10.5-11.25a.75.75 0 01.913-.143z" clip-rule="evenodd" />
                    </svg>
                    Free Trial
                </span>
            </div>
            <p class="mt-2 text-sm" style="color: #A1A1AA;">
                Start your 14-day free trial. No commitment required.
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
                <span class="px-2 text-xs" style="background: rgba(9, 9, 11, 0.8); color: #A1A1AA;">Or continue with email</span>
            </div>
        </div>

        <form method="POST" action="{{ route('register') }}" class="space-y-4">
            @csrf
            <div class="grid grid-cols-2 gap-4">
                <!-- Name -->
                <div class="space-y-2">
                    <label for="name" class="block text-sm font-medium" style="color: #FAFAFA;">
                        Full Name
                    </label>
                    <input id="name" name="name" type="text" autocomplete="name" required
                        value="{{ old('name') }}"
                        placeholder="Sarah Johnson"
                        class="flex w-full rounded-lg text-base transition-all duration-200 file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-gray-400 focus:outline-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm backdrop-blur-sm @error('name') border-red-500 focus:border-red-500 focus:ring-2 focus:ring-red-500/50 @else focus:border-purple-500 focus:ring-2 focus:ring-purple-500/50 @enderror"
                        style="background: rgba(9, 9, 11, 0.8); border: 1px solid #27272A; color: #FAFAFA; height: 3.5rem !important; padding: 1rem 1.5rem !important;">
                    @error('name')
                        <p class="text-sm" style="color: #EF4444;">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Role -->
                <div class="space-y-2">
                    <label for="role" class="block text-sm font-medium" style="color: #FAFAFA;">
                        Role
                    </label>
                    <select id="role" name="role" required
                        class="flex w-full rounded-lg text-base transition-all duration-200 focus:outline-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm backdrop-blur-sm @error('role') border-red-500 focus:border-red-500 focus:ring-2 focus:ring-red-500/50 @else focus:border-purple-500 focus:ring-2 focus:ring-purple-500/50 @enderror"
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
                    @error('role')
                        <p class="text-sm" style="color: #EF4444;">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <!-- Email Address -->
            <div class="space-y-2">
                <label for="email" class="block text-sm font-medium" style="color: #FAFAFA;">
                    Work Email
                </label>
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
            <div class="space-y-2">
                <label for="password" class="block text-sm font-medium" style="color: #FAFAFA;">
                    Password
                </label>
                <input id="password" name="password" type="password" autocomplete="new-password" required
                    placeholder="Min. 8 characters"
                    class="flex w-full rounded-lg text-base transition-all duration-200 file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-gray-400 focus:outline-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm backdrop-blur-sm @error('password') border-red-500 focus:border-red-500 focus:ring-2 focus:ring-red-500/50 @else focus:border-purple-500 focus:ring-2 focus:ring-purple-500/50 @enderror"
                    style="background: rgba(9, 9, 11, 0.8); border: 1px solid #27272A; color: #FAFAFA; height: 3.5rem !important; padding: 1rem 1.5rem !important;">
                @error('password')
                    <p class="text-sm" style="color: #EF4444;">{{ $message }}</p>
                @enderror
            </div>

            <!-- How did you hear about us -->
            <div class="space-y-2">
                <label for="source" class="block text-sm font-medium" style="color: #FAFAFA;">
                    How did you hear about us?
                </label>
                <select id="source" name="source"
                    class="flex w-full rounded-lg text-base transition-all duration-200 focus:outline-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm backdrop-blur-sm @error('source') border-red-500 focus:border-red-500 focus:ring-2 focus:ring-red-500/50 @else focus:border-purple-500 focus:ring-2 focus:ring-purple-500/50 @enderror"
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
                @error('source')
                    <p class="text-sm" style="color: #EF4444;">{{ $message }}</p>
                @enderror
            </div>

            <!-- Submit Button -->
            <button type="submit" class="w-full text-lg flex items-center justify-center gap-2 purple-gradient-button rounded-lg font-medium text-white transition-all duration-200 transform hover:scale-[1.02] focus:outline-none focus:ring-2 focus:ring-purple-500 focus:ring-offset-2 focus:ring-offset-gray-900" style="height: 3.5rem !important; padding: 1rem 1.5rem !important;">
                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                    <path fill-rule="evenodd" d="M10.5 3.75a6 6 0 00-5.98 6.496A5.25 5.25 0 006.75 20.25H16.5a4.5 4.5 0 003.256-7.606 3 3 0 00-1.787-5.392A6.016 6.016 0 0010.5 3.75zm2.03 5.47a.75.75 0 00-1.06 0l-3 3a.75.75 0 101.06 1.06l1.72-1.72v4.94a.75.75 0 001.5 0v-4.94l1.72 1.72a.75.75 0 101.06-1.06l-3-3z" clip-rule="evenodd" />
                </svg>
                Start Free Trial
            </button>

            <p class="text-xs text-center" style="color: #A1A1AA;">
                By signing up, you agree to our
                <a href="#" class="underline" style="color: #884DFF;">Terms</a> and
                <a href="#" class="underline" style="color: #884DFF;">Privacy Policy</a>
            </p>
        </form>
    </div>

    <div class="text-center mt-6">
        <p class="text-sm" style="color: #A1A1AA;">
            Already have an account?
            <a href="{{ route('login') }}" class="font-medium hover:underline" style="color: #884DFF;">
                Sign in
            </a>
        </p>
    </div>
</div>
@endsection