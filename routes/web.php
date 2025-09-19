<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\WorkstreamsController;
use App\Http\Controllers\ReleaseController;
use App\Http\Controllers\QuickAddController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

// Simple login route for testing
Route::get('/login', function () {
    Auth::loginUsingId(1); // Login as user ID 1
    return redirect('/');
})->name('login');

// Auto-login route for testing
Route::get('/', function () {
    if (!Auth::check()) {
        $user = App\Models\User::first();
        if (!$user) {
            // Create a default user if none exists
            $user = App\Models\User::create([
                'name' => 'Aaron Middleton',
                'email' => 'aaronmiddleton@gmail.com',
                'password' => Hash::make('Test123'),
            ]);
        }
        Auth::login($user);
    }
    return app(App\Http\Controllers\DashboardController::class)->index(request());
})->name('dashboard');

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

    Route::prefix('quick-add')->name('quick-add')->group(function () {
        Route::get('/', [QuickAddController::class, 'index']);
        Route::post('/', [QuickAddController::class, 'process']);
        Route::post('/convert-to-release', [QuickAddController::class, 'convertToRelease']);
        Route::get('/select-release', [QuickAddController::class, 'selectRelease']);
    });
});
