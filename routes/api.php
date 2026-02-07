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

    Route::prefix('daily-number')->group(function () {
        Route::get('/', [\App\Http\Controllers\DailyNumberController::class, 'index'])
            ->middleware('permission:daily_number.index')
            ->name('daily_number.index');
        Route::post('/', [\App\Http\Controllers\DailyNumberController::class, 'store'])
            ->middleware('permission:daily_number.create')
            ->name('daily_number.create');
        Route::get('/{id}', [\App\Http\Controllers\DailyNumberController::class, 'show'])
            ->middleware('permission:daily_number.show')
            ->name('daily_number.show');
        Route::delete('/{id}', [\App\Http\Controllers\DailyNumberController::class, 'destroy'])
            ->middleware('permission:daily_number.delete')
            ->name('daily_number.delete');
        Route::patch('/{id}', [\App\Http\Controllers\DailyNumberController::class, 'update'])
            ->middleware('permission:daily_number.edit')
            ->name('daily_number.edit');
    });

    Route::prefix('transaction')->group(function () {
        Route::get('/', [\App\Http\Controllers\TransactionController::class, 'index'])
            ->middleware('permission:transaction.index')
            ->name('transaction.index');
        Route::post('/', [\App\Http\Controllers\TransactionController::class, 'store'])
            ->middleware('permission:transaction.create')
            ->name('transaction.create');
        Route::get('/{id}', [\App\Http\Controllers\TransactionController::class, 'show'])
            ->middleware('permission:transaction.show')
            ->name('transaction.show');
        Route::delete('/{id}', [\App\Http\Controllers\TransactionController::class, 'destroy'])
            ->middleware('permission:transaction.delete')
            ->name('transaction.delete');
        Route::patch('/{id}', [\App\Http\Controllers\TransactionController::class, 'update'])
            ->middleware('permission:transaction.edit')
            ->name('daily_number.edit');
        Route::get('/get-balance-user/{id}', [\App\Http\Controllers\TransactionController::class, 'getBalanceByUser'])
            ->middleware('permission:transaction.get_balance')
            ->name('transaction.get_balance');
    });
    Route::prefix('config-admin')->group(function () {
        Route::get('/', [\App\Http\Controllers\AdminConfigController::class, 'index'])
            ->middleware('permission:config_admin.index')
            ->name('config_admin.index');
        Route::post('/', [\App\Http\Controllers\AdminConfigController::class, 'store'])
            ->middleware('permission:config_admin.create')
            ->name('config_admin.create');
        Route::get('/{id}', [\App\Http\Controllers\AdminConfigController::class, 'show'])
            ->middleware('permission:config_admin.show')
            ->name('config_admin.show');
        Route::delete('/{id}', [\App\Http\Controllers\AdminConfigController::class, 'destroy'])
            ->middleware('permission:config_admin.delete')
            ->name('config_admin.delete');
        Route::patch('/{id}', [\App\Http\Controllers\AdminConfigController::class, 'update'])
            ->middleware('permission:config_admin.edit')
            ->name('config_admin.edit');
    });

    Route::get('/user-permissions', [\App\Http\Controllers\UserController::class, 'userPermissions']);
    Route::get('/log-activity', [\App\Http\Controllers\ActivityLogController::class, 'index']);

});




