<?php

use App\Http\Controllers\Api\ApprovalRequestController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BrainDumpController;
use App\Http\Controllers\Api\ChecklistAssignmentController;
use App\Http\Controllers\Api\ChecklistDependencyController;
use App\Http\Controllers\Api\CommunicationController;
use App\Http\Controllers\Api\ContentController;
use App\Http\Controllers\Api\ReleaseStakeholderController;
use App\Http\Controllers\Api\StakeholderReleaseController;
use App\Http\Controllers\Api\WorkstreamController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Authentication routes (public)
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Authenticated user routes
Route::middleware('auth:sanctum')->group(function () {
    // Authentication routes that require authentication
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);
    Route::put('/user', [AuthController::class, 'updateProfile']);
    Route::put('/user/password', [AuthController::class, 'updatePassword']);
});

// All API routes require authentication
Route::middleware('auth:sanctum')->group(function () {
    // Content management routes
    Route::apiResource('content', ContentController::class);
    Route::post('content/{content}/reprocess', [ContentController::class, 'reprocess']);
    Route::get('content/{content}/analysis', [ContentController::class, 'analysis']);

    // Brain dump processing routes
    Route::post('brain-dump/process', [BrainDumpController::class, 'process']);
    // Release stakeholder management routes
    Route::prefix('releases/{release}')->group(function () {
        Route::get('stakeholders', [ReleaseStakeholderController::class, 'index']);
        Route::post('stakeholders', [ReleaseStakeholderController::class, 'store']);
        Route::put('stakeholders/{stakeholder}', [ReleaseStakeholderController::class, 'update']);
        Route::delete('stakeholders/{stakeholder}', [ReleaseStakeholderController::class, 'destroy']);
    });

    // Stakeholder releases routes
    Route::get('stakeholders/{stakeholder}/releases', [StakeholderReleaseController::class, 'index']);

    // Checklist assignment management routes
    Route::prefix('releases/{release}')->group(function () {
        Route::post('checklist-assignments', [ChecklistAssignmentController::class, 'store']);
        Route::get('checklist-assignments', [ChecklistAssignmentController::class, 'index']);
    });

    Route::prefix('checklist-assignments')->group(function () {
        Route::get('{assignment}', [ChecklistAssignmentController::class, 'show']);
        Route::put('{assignment}/reassign', [ChecklistAssignmentController::class, 'reassign']);
        Route::post('{assignment}/escalate', [ChecklistAssignmentController::class, 'escalate']);
        Route::put('{assignment}/status', [ChecklistAssignmentController::class, 'updateStatus']);
    });

    // Checklist dependency management routes
    Route::post('checklist-dependencies', [ChecklistDependencyController::class, 'store']);
    Route::prefix('checklist-dependencies')->group(function () {
        Route::get('{dependency}', [ChecklistDependencyController::class, 'show']);
        Route::put('{dependency}', [ChecklistDependencyController::class, 'update']);
        Route::delete('{dependency}', [ChecklistDependencyController::class, 'destroy']);
        Route::get('assignment/{assignment}', [ChecklistDependencyController::class, 'getForAssignment']);
    });

    // Bulk workstream operations (must come before resourceful routes)
    Route::put('workstreams/bulk-update', [WorkstreamController::class, 'bulkUpdate']);

    // Workstream management routes
    Route::apiResource('workstreams', WorkstreamController::class);

    // Additional workstream hierarchy routes
    Route::prefix('workstreams')->group(function () {
        Route::get('{workstream}/hierarchy', [WorkstreamController::class, 'hierarchy']);
        Route::get('{workstream}/rollup-report', [WorkstreamController::class, 'rollupReport']);
        Route::get('{workstream}/permissions', [WorkstreamController::class, 'permissions']);
        Route::post('{workstream}/permissions', [WorkstreamController::class, 'storePermissions']);
        Route::put('{workstream}/move', [WorkstreamController::class, 'move']);
        Route::get('{workstream}/approval-summary', [ApprovalRequestController::class, 'workstreamSummary']);
    });

    // Approval workflow routes
    Route::prefix('releases/{release}')->group(function () {
        Route::post('approval-requests', [ApprovalRequestController::class, 'storeForRelease']);
        Route::get('approval-requests', [ApprovalRequestController::class, 'indexForRelease']);
        Route::get('approval-status', [ApprovalRequestController::class, 'statusForRelease']);
    });

    Route::prefix('approval-requests')->group(function () {
        Route::post('send-reminders', [ApprovalRequestController::class, 'sendReminders']);
        Route::post('process-expirations', [ApprovalRequestController::class, 'processExpirations']);
        Route::put('{approvalRequest}', [ApprovalRequestController::class, 'update']);
        Route::post('{approvalRequest}/respond', [ApprovalRequestController::class, 'respond']);
        Route::post('{approvalRequest}/cancel', [ApprovalRequestController::class, 'cancel']);
    });

    // Communication audit trail routes
    Route::prefix('releases/{release}')->group(function () {
        Route::post('communications', [CommunicationController::class, 'storeForRelease']);
        Route::get('communications', [CommunicationController::class, 'indexForRelease']);
        Route::get('communication-analytics', [CommunicationController::class, 'analyticsForRelease']);
    });

    Route::prefix('communications')->group(function () {
        Route::get('search', [CommunicationController::class, 'search']);
        Route::get('follow-ups', [CommunicationController::class, 'getFollowUps']);
        Route::get('{communication}', [CommunicationController::class, 'show']);
        Route::put('{communication}/outcome', [CommunicationController::class, 'updateOutcome']);
        Route::put('{communication}/participants/{participant}/status', [CommunicationController::class, 'updateParticipantStatus']);
    });
});