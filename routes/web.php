<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\WorkstreamsController;
use App\Http\Controllers\ReleaseController;
use App\Http\Controllers\StakeholderController;
use App\Http\Controllers\ContentController;
use App\Http\Controllers\DesignSystemController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Api\AuthController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;

// Authentication Routes (Public) - Apply web middleware group for session handling
Route::middleware(['web'])->group(function () {
    Route::get('/login', [LoginController::class, 'showLoginForm'])->middleware('guest')->name('login');
    Route::post('/login', [LoginController::class, 'login'])->middleware('guest');
    Route::get('/register', [RegisterController::class, 'showRegistrationForm'])->middleware('guest')->name('register');
    Route::post('/register', [RegisterController::class, 'register'])->middleware('guest');
    Route::get('/verify-email', function () {
        return view('auth.verify-email', ['userEmail' => request()->query('email', 'your email')]);
    })->name('verify-email');

    // Firebase email verification callback
    Route::get('/auth/verify-email-callback', function () {
        return view('auth.verify-email-callback');
    })->name('verify-email-callback');

    // Firebase magic link callback
    Route::get('/auth/magic-link-callback', function () {
        return view('auth.magic-link-callback');
    })->name('magic-link-callback');

    // Firebase auth endpoint for web (session-based)
    Route::post('/auth/firebase', [AuthController::class, 'firebase'])->name('auth.firebase');
    Route::post('/logout', [LoginController::class, 'logout'])->middleware('auth')->name('logout');
});

// Password Reset Routes - Also need web middleware
Route::middleware(['web'])->group(function () {
    Route::get('/forgot-password', function () {
        return view('auth.forgot-password');
    })->middleware('guest')->name('password.request');

    Route::get('/reset-password/{token}', function (string $token) {
        return view('auth.reset-password', ['token' => $token]);
    })->middleware('guest')->name('password.reset');
});


// Root route - redirect to dashboard if authenticated, login if not
Route::get('/', function () {
    if (Auth::check()) {
        return redirect()->route('dashboard');
    }
    return redirect()->route('login');
});

// Dashboard route (protected)
Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'firebase.verified'])
    ->name('dashboard');

// Public routes (accessible without authentication)
Route::get('/design-system', [DesignSystemController::class, 'index'])->name('design-system');

Route::middleware(['auth', 'firebase.verified'])->group(function () {
    Route::prefix('workstreams')->name('workstreams.')->group(function () {
        Route::get('/', [WorkstreamsController::class, 'index'])->name('index');
        Route::get('/{workstream}', [WorkstreamsController::class, 'show'])->name('show');
        Route::post('/', [WorkstreamsController::class, 'store'])->name('store');
        Route::put('/{workstream}', [WorkstreamsController::class, 'update'])->name('update');
    });

    Route::prefix('releases')->name('releases.')->group(function () {
        Route::get('/', [ReleaseController::class, 'index'])->name('index');
        Route::get('/{release}', [ReleaseController::class, 'show'])->name('show');
        Route::get('/{release}/stakeholders', [ReleaseController::class, 'stakeholders'])->name('stakeholders');
        Route::post('/{release}/stakeholders', [ReleaseController::class, 'storeStakeholder'])->name('stakeholders.store');
        Route::post('/{release}/communications', [ReleaseController::class, 'storeCommunication'])->name('communications.store');
        Route::patch('/{release}/status', [ReleaseController::class, 'updateStatus'])->name('update-status');
        Route::post('/{release}/tasks', [ReleaseController::class, 'storeTasks'])->name('tasks.store');
        Route::patch('/{release}/tasks/bulk', [ReleaseController::class, 'bulkUpdateTasks'])->name('tasks.bulk-update');
    });

    Route::prefix('stakeholders')->name('stakeholders.')->group(function () {
        Route::get('/', [StakeholderController::class, 'index'])->name('index');
        Route::get('/create', [StakeholderController::class, 'create'])->name('create');
        Route::post('/', [StakeholderController::class, 'store'])->name('store');
        Route::get('/{stakeholder}', [StakeholderController::class, 'show'])->name('show');
        Route::get('/{stakeholder}/edit', [StakeholderController::class, 'edit'])->name('edit');
        Route::put('/{stakeholder}', [StakeholderController::class, 'update'])->name('update');
        Route::delete('/{stakeholder}', [StakeholderController::class, 'destroy'])->name('destroy');
        Route::patch('/{stakeholder}/contact', [StakeholderController::class, 'updateLastContact'])->name('update-contact');
    });

    // Content management routes
    Route::prefix('content')->name('content.')->group(function () {
        Route::get('/', [ContentController::class, 'index'])->name('index');
        Route::get('/{content}', [ContentController::class, 'show'])->name('show');
        Route::delete('/{content}', [ContentController::class, 'destroy'])->name('destroy');
    });

    // Profile routes
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
});
