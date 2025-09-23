<?php

namespace App\Providers;

// use Illuminate\Support\Facades\Gate;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        \App\Models\User::class => \App\Policies\UserPolicy::class,
        \App\Models\Workstream::class => \App\Policies\WorkstreamPolicy::class,
        \App\Models\Release::class => \App\Policies\ReleasePolicy::class,
        \App\Models\Stakeholder::class => \App\Policies\StakeholderPolicy::class,
        \App\Models\Content::class => \App\Policies\ContentPolicy::class,
        \App\Models\Feedback::class => \App\Policies\FeedbackPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        //
    }
}