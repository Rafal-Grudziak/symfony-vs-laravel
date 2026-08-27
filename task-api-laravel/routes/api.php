<?php

use App\Http\Controllers\Api\BenchmarkController;
use App\Http\Controllers\Api\CommentController;
use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\TagController;
use App\Http\Controllers\Api\TaskController;
use Illuminate\Support\Facades\Route;

Route::apiResource('projects', ProjectController::class);
Route::apiResource('tasks', TaskController::class);
Route::apiResource('comments', CommentController::class)->except(['store']);
Route::apiResource('tags', TagController::class);

Route::post('tasks/{task}/comments', [CommentController::class, 'storeForTask']);

Route::prefix('reports')->group(function (): void {
    Route::get('tasks-per-project', [ReportController::class, 'tasksPerProject']);
    Route::get('top-projects', [ReportController::class, 'topProjects']);
    Route::get('complex-task-overview', [ReportController::class, 'complexTaskOverview']);
});

Route::prefix('benchmark')->group(function (): void {
    Route::post('bulk-tasks', [BenchmarkController::class, 'bulkTasks']);
    Route::post('bulk-comments', [BenchmarkController::class, 'bulkComments']);

    Route::prefix('no-orm')->group(function (): void {
        Route::post('warmup', \App\Http\Controllers\Api\Benchmark\NoOrmWarmupController::class);
        Route::get('tasks', [\App\Http\Controllers\Api\Benchmark\NoOrmTaskController::class, 'index']);
        Route::post('tasks', [\App\Http\Controllers\Api\Benchmark\NoOrmTaskController::class, 'store']);
        Route::get('tasks/{id}', [\App\Http\Controllers\Api\Benchmark\NoOrmTaskController::class, 'show'])
            ->whereNumber('id');
        Route::put('tasks/{id}', [\App\Http\Controllers\Api\Benchmark\NoOrmTaskController::class, 'update'])
            ->whereNumber('id');
        Route::delete('tasks/{id}', [\App\Http\Controllers\Api\Benchmark\NoOrmTaskController::class, 'destroy'])
            ->whereNumber('id');
    });
});
