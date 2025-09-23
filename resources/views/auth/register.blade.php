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

        <!-- Firebase Complete Registration Form -->
        <firebase-register-form></firebase-register-form>
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