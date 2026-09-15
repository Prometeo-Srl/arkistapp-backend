<?php

use App\Http\Controllers\ChecklistAssignmentController;
use App\Http\Controllers\ChecklistController;
use App\Http\Controllers\ChecklistExecutionController;
use App\Http\Controllers\ChecklistSectionController;
use App\Http\Controllers\ChecklistStructureController;
use Illuminate\Support\Facades\Route;

// Checklists: authored by the DDL as a bozza, shared once - which publishes them
// and assigns them in the same act - and answered by the named people it reached.
Route::middleware('auth:sanctum')->group(function () {
    // Authoring.
    Route::get('/companies/{company}/checklists', [ChecklistController::class, 'index']);
    Route::post('/companies/{company}/checklists', [ChecklistController::class, 'store']);
    Route::get('/checklists/{checklist}', [ChecklistController::class, 'show']);
    Route::patch('/checklists/{checklist}', [ChecklistController::class, 'update']);
    Route::delete('/checklists/{checklist}', [ChecklistController::class, 'destroy']);
    Route::post('/checklists/{checklist}/duplicate', [ChecklistController::class, 'duplicate']);
    Route::get('/checklists/{checklist}/pdf', [ChecklistController::class, 'pdf']);

    // The builder's one save: the whole tree, reconciled on client uuids (ADR-0004).
    Route::put('/checklists/{checklist}/structure', [ChecklistStructureController::class, 'update']);
    Route::post('/checklists/{checklist}/images', [ChecklistStructureController::class, 'storeImage']);
    Route::get('/checklists/{checklist}/images/{name}', [ChecklistStructureController::class, 'showImage'])
        ->where('name', '[A-Za-z0-9._-]+');

    // The granular write path, one node at a time. Kept, not deprecated.
    Route::post('/checklists/{checklist}/sections', [ChecklistSectionController::class, 'store']);
    Route::patch('/checklists/sections/{section}', [ChecklistSectionController::class, 'update']);
    Route::delete('/checklists/sections/{section}', [ChecklistSectionController::class, 'destroy']);
    Route::post('/checklists/sections/{section}/questions', [ChecklistSectionController::class, 'storeQuestion']);
    Route::patch('/checklists/questions/{question}', [ChecklistSectionController::class, 'updateQuestion']);
    Route::delete('/checklists/questions/{question}', [ChecklistSectionController::class, 'destroyQuestion']);
    Route::post('/checklists/questions/{question}/duplicate', [ChecklistSectionController::class, 'duplicateQuestion']);

    // Condivisione, and the oversight it produces. There is no publish endpoint:
    // publishing without assigning is not a state the system can reach (ADR-0005).
    Route::post('/checklists/{checklist}/share', [ChecklistAssignmentController::class, 'share']);
    Route::get('/checklists/{checklist}/assignments', [ChecklistAssignmentController::class, 'index']);
    Route::delete('/assignments/{assignment}', [ChecklistAssignmentController::class, 'destroy']);

    // Compilazione: the worker's queue and the one hand-over.
    Route::get('/my/checklists', [ChecklistExecutionController::class, 'mine']);
    Route::post('/assignments/{assignment}/start', [ChecklistExecutionController::class, 'start']);
    Route::post('/assignments/{assignment}/submit', [ChecklistExecutionController::class, 'submit']);
    Route::get('/submissions/{submission}', [ChecklistExecutionController::class, 'show']);
});
