<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/login', [\App\Http\Controllers\AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::prefix('list')->group(function () {
        Route::post('/process', [\App\Http\Controllers\ListController::class, 'process'])
            ->middleware('permission:list.process')
            ->name('list.process');

        Route::post('/preview', [\App\Http\Controllers\ListController::class, 'preview'])
            ->middleware('permission:list.process')
            ->name('list.preview');

        Route::post('/validate', [\App\Http\Controllers\ListController::class, 'validate'])
            ->middleware('permission:list.validate')
            ->name('list.validate');
    });

    Route::get('/user-permissions', [\App\Http\Controllers\UserController::class, 'userPermissions']);


});




