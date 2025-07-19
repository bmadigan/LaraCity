<?php

declare(strict_types=1);

use App\Http\Controllers\Api\ComplaintController;
use App\Http\Controllers\Api\ActionController;
use App\Http\Controllers\Api\UserQuestionController;
use App\Http\Controllers\Api\SemanticSearchController;
use Illuminate\Support\Facades\Route;

// Authenticated API routes
Route::middleware(['auth:sanctum'])->group(function () {
    
    // Complaint endpoints
    Route::get('/complaints', [ComplaintController::class, 'index'])
        ->name('api.complaints.index');
    
    Route::get('/complaints/summary', [ComplaintController::class, 'summary'])
        ->name('api.complaints.summary');
    
    Route::get('/complaints/{complaint}', [ComplaintController::class, 'show'])
        ->name('api.complaints.show');
    
    // Action endpoints
    Route::post('/actions/escalate', [ActionController::class, 'escalate'])
        ->name('api.actions.escalate');
    
    // User question endpoints  
    Route::post('/user-questions', [UserQuestionController::class, 'store'])
        ->name('api.user-questions.store');
    
    // Semantic search endpoints
    Route::prefix('search')->name('api.search.')->group(function () {
        Route::post('/semantic', [SemanticSearchController::class, 'search'])
            ->name('semantic');
        
        Route::post('/similar', [SemanticSearchController::class, 'similar'])
            ->name('similar');
        
        Route::post('/embed', [SemanticSearchController::class, 'embed'])
            ->name('embed');
        
        Route::get('/stats', [SemanticSearchController::class, 'stats'])
            ->name('stats');
        
        Route::get('/test', [SemanticSearchController::class, 'test'])
            ->name('test');
    });
});