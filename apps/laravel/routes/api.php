<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SmbController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::prefix('files')->group(function () {
    Route::get('/', [SmbController::class, 'list']);
    Route::post('/upload', [SmbController::class, 'upload']);
    Route::get('/download', [SmbController::class, 'download']);
    Route::post('/folder', [SmbController::class, 'createFolder']);
    Route::delete('/', [SmbController::class, 'delete']);
    Route::put('/rename', [SmbController::class, 'rename']);
});
