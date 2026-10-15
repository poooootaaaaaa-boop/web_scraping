<?php

use App\Http\Controllers\Api\ExecutionController;
use App\Http\Controllers\Api\FieldController;
use App\Http\Controllers\Api\HistoryController;
use App\Http\Controllers\Api\LegacyDataController;
use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\Api\TaskController;
use Illuminate\Support\Facades\Route;

Route::middleware('throttle:api')->group(function () {
    Route::get('data/export', [LegacyDataController::class, 'export']);
    Route::post('data/import/{resource}', [LegacyDataController::class, 'import']);
    Route::apiResource('projects', ProjectController::class);
    Route::get('projects/{project}/fields', [FieldController::class, 'index']);
    Route::post('projects/{project}/fields', [FieldController::class, 'store']);
    Route::get('fields/{field}', [FieldController::class, 'show']);
    Route::put('fields/{field}', [FieldController::class, 'update']);
    Route::patch('fields/{field}', [FieldController::class, 'update']);
    Route::delete('fields/{field}', [FieldController::class, 'destroy']);
    Route::get('projects/{project}/tasks', [TaskController::class, 'index']);
    Route::post('projects/{project}/tasks', [TaskController::class, 'store']);
    Route::get('tasks', [TaskController::class, 'all']);
    Route::get('tasks/{task}', [TaskController::class, 'show']);
    Route::get('tasks/{task}/executions', [ExecutionController::class, 'byTask']);
    Route::put('tasks/{task}', [TaskController::class, 'update']);
    Route::patch('tasks/{task}', [TaskController::class, 'update']);
    Route::patch('tasks/{task}/pause', [TaskController::class, 'pause']);
    Route::patch('tasks/{task}/resume', [TaskController::class, 'resume']);
    Route::patch('tasks/{task}/schedule', [TaskController::class, 'schedule']);
    Route::delete('tasks/{task}', [TaskController::class, 'destroy']);
    Route::get('executions/statistics', [ExecutionController::class, 'statistics']);
    Route::get('executions', [ExecutionController::class, 'all']);
    Route::get('projects/{project}/executions', [ExecutionController::class, 'index']);
    Route::post('executions', [ExecutionController::class, 'store']);
    Route::get('executions/{execution}', [ExecutionController::class, 'show']);
    Route::patch('executions/{execution}/complete', [ExecutionController::class, 'complete']);
    Route::post('executions/{execution}/values', [ExecutionController::class, 'storeValues']);
    Route::get('executions/{execution}/values', [ExecutionController::class, 'values']);
    Route::get('projects/{project}/history', [HistoryController::class, 'index']);
    Route::get('projects/{project}/history/{executionId}', [HistoryController::class, 'byExecution']);
    Route::get('projects/{project}/prices', [HistoryController::class, 'prices']);
    Route::get('projects/{project}/prices/statistics', [HistoryController::class, 'statistics']);
    Route::get('fields/{field}/prices', [HistoryController::class, 'fieldPrices']);
    Route::get('fields/{field}/prices/statistics', [HistoryController::class, 'fieldStatistics']);
});