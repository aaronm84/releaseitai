<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\WorkstreamsController;
use App\Http\Controllers\ReleaseController;
use App\Http\Controllers\StakeholderController;
use App\Http\Controllers\DesignSystemController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;

// Authentication Routes (Public)
Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
Route::post('/login', [LoginController::class, 'login']);
Route::get('/register', [RegisterController::class, 'showRegistrationForm'])->name('register');
Route::post('/register', [RegisterController::class, 'register']);
Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

// Root route - redirect to dashboard if authenticated, login if not
Route::get('/', function () {
    if (Auth::check()) {
        return redirect()->route('dashboard');
    }
    return redirect()->route('login');
});

// Dashboard route (protected)
Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware('auth')
    ->name('dashboard');

// Public routes (accessible without authentication)
Route::get('/design-system', [DesignSystemController::class, 'index'])->name('design-system');

Route::middleware('auth')->group(function () {
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
});
