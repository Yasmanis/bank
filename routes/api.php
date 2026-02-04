<?php

use Illuminate\Support\Facades\Route;

Route::post('/login', [\App\Http\Controllers\AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::prefix('list')->group(function () {
        Route::get('/',[\App\Http\Controllers\BankListController::class, 'index']);
        Route::get('/{id}',[\App\Http\Controllers\BankListController::class, 'show']);
        Route::delete('/{id}',[\App\Http\Controllers\BankListController::class, 'destroy']);
        Route::post('/process', [\App\Http\Controllers\BankListController::class, 'process'])
            ->middleware('permission:list.process')
            ->name('list.process');

        Route::post('/preview', [\App\Http\Controllers\BankListController::class, 'preview'])
            ->middleware('permission:list.preview')
            ->name('list.preview');

        Route::get('/preview/{id}', [\App\Http\Controllers\BankListController::class, 'previewById'])
            ->middleware('permission:list.preview')
            ->name('list.preview');

        Route::post('/validate/{id}', [\App\Http\Controllers\BankListController::class, 'validate'])
            ->middleware('permission:list.validate')
            ->name('list.validate');
    });

    Route::get('/user-permissions', [\App\Http\Controllers\UserController::class, 'userPermissions']);
    Route::get('/log-activity', [\App\Http\Controllers\ActivityLogController::class, 'index']);

});




