<?php

use App\Http\Controllers\AccessGrantController;
use App\Http\Controllers\BrandingController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\FileController;
use App\Http\Controllers\FolderController;
use Illuminate\Support\Facades\Route;

// Documents / Archive slice: categories, folders, files, sharing and branding.
Route::middleware('auth:sanctum')->group(function () {
    // Reference data: document types drive the automatic expiry calculation.
    Route::get('/document-types', [FileController::class, 'documentTypes']);

    // Archive tree.
    Route::get('/companies/{company}/categories', [CategoryController::class, 'index']);
    Route::post('/companies/{company}/categories', [CategoryController::class, 'store']);
    Route::patch('/categories/{category}', [CategoryController::class, 'update']);
    Route::delete('/categories/{category}', [CategoryController::class, 'destroy']);

    Route::get('/companies/{company}/folders', [FolderController::class, 'index']);
    Route::post('/companies/{company}/folders', [FolderController::class, 'store']);
    Route::patch('/folders/{folder}', [FolderController::class, 'update']);
    Route::delete('/folders/{folder}', [FolderController::class, 'destroy']);
    // The whole branch as one zip; there is no per-file walk on the client.
    Route::get('/folders/{folder}/download', [FolderController::class, 'download']);

    // Documents (multipart upload, download, versions, acknowledgements).
    Route::get('/companies/{company}/files', [FileController::class, 'index']);
    Route::post('/files', [FileController::class, 'store']);
    Route::get('/files/{file}', [FileController::class, 'show']);
    Route::get('/files/{file}/download', [FileController::class, 'download']);
    Route::patch('/files/{file}', [FileController::class, 'update']);
    Route::delete('/files/{file}', [FileController::class, 'destroy']);
    Route::post('/files/{file}/versions', [FileController::class, 'storeVersion']);
    Route::post('/files/{file}/acknowledge', [FileController::class, 'acknowledge']);
    Route::get('/files/{file}/acknowledgements', [FileController::class, 'acknowledgements']);

    // Granular sharing.
    Route::get('/grants', [AccessGrantController::class, 'index']);
    Route::post('/grants', [AccessGrantController::class, 'store']);
    Route::delete('/grants/{grant}', [AccessGrantController::class, 'destroy']);

    // Tenant look & feel.
    Route::get('/companies/{company}/branding', [BrandingController::class, 'show']);
    Route::patch('/companies/{company}/branding', [BrandingController::class, 'update']);
});
