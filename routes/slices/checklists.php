<?php

use App\Http\Controllers\ChecklistAssignmentController;
use App\Http\Controllers\ChecklistController;
use App\Http\Controllers\ChecklistExecutionController;
use App\Http\Controllers\ChecklistSectionController;
use Illuminate\Support\Facades\Route;

// Checklist templates: built by company admins, carried out by assignees.
Route::middleware('auth:sanctum')->group(function () {
    // Template management.
    Route::get('/companies/{company}/checklists', [ChecklistController::class, 'index']);
    Route::post('/companies/{company}/checklists', [ChecklistController::class, 'store']);
    Route::get('/checklists/{checklist}', [ChecklistController::class, 'show']);
    Route::patch('/checklists/{checklist}', [ChecklistController::class, 'update']);
    Route::delete('/checklists/{checklist}', [ChecklistController::class, 'destroy']);
    Route::post('/checklists/{checklist}/publish', [ChecklistController::class, 'publish']);

    // Template structure: sections and their questions.
    Route::post('/checklists/{checklist}/sections', [ChecklistSectionController::class, 'store']);
    Route::patch('/checklists/sections/{section}', [ChecklistSectionController::class, 'update']);
    Route::delete('/checklists/sections/{section}', [ChecklistSectionController::class, 'destroy']);
    Route::post('/checklists/sections/{section}/questions', [ChecklistSectionController::class, 'storeQuestion']);
    Route::patch('/checklists/questions/{question}', [ChecklistSectionController::class, 'updateQuestion']);
    Route::delete('/checklists/questions/{question}', [ChecklistSectionController::class, 'destroyQuestion']);

    // Assignments.
    Route::get('/checklists/{checklist}/assignments', [ChecklistAssignmentController::class, 'index']);
    Route::post('/checklists/{checklist}/assignments', [ChecklistAssignmentController::class, 'store']);
    Route::delete('/assignments/{assignment}', [ChecklistAssignmentController::class, 'destroy']);

    // Execution: the assignee's queue and the submission lifecycle.
    Route::get('/my/checklists', [ChecklistExecutionController::class, 'mine']);
    Route::post('/assignments/{assignment}/start', [ChecklistExecutionController::class, 'start']);
    Route::post('/assignments/{assignment}/submit', [ChecklistExecutionController::class, 'submit']);
    Route::get('/submissions/{submission}', [ChecklistExecutionController::class, 'show']);
});
