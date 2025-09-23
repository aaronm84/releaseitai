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

        <!-- Firebase Complete Auth Form -->
        <firebase-login-form></firebase-login-form>
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
@endsection