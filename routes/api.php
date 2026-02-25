<?php

use Illuminate\Support\Facades\Route;

Route::post('/login', [\App\Http\Controllers\AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/refresh', [\App\Http\Controllers\AuthController::class, 'refresh']);
    Route::get('/check-time', fn() => now()->toDateTimeString());
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
            ->middleware('permission:admin_config.index')
            ->name('admin_config.index');
        Route::post('/', [\App\Http\Controllers\AdminConfigController::class, 'store'])
            ->middleware('permission:admin_config.create')
            ->name('admin_config.create');
        Route::get('/{id}', [\App\Http\Controllers\AdminConfigController::class, 'show'])
            ->middleware('permission:admin_config.show')
            ->name('admin_config.show');
        Route::delete('/{id}', [\App\Http\Controllers\AdminConfigController::class, 'destroy'])
            ->middleware('permission:admin_config.delete')
            ->name('admin_config.delete');
        Route::patch('/{id}', [\App\Http\Controllers\AdminConfigController::class, 'update'])
            ->middleware('permission:admin_config.edit')
            ->name('admin_config.edit');
    });

    Route::prefix('config-user')->group(function () {
        Route::get('/', [\App\Http\Controllers\UserConfigController::class, 'index'])
            ->middleware('permission:user_config.index')
            ->name('user_config.index');
        Route::post('/', [\App\Http\Controllers\UserConfigController::class, 'store'])
            ->middleware('permission:user_config.create')
            ->name('user_config.create');
        Route::get('/{id}', [\App\Http\Controllers\UserConfigController::class, 'show'])
            ->middleware('permission:user_config.show')
            ->name('user_config.show');
        Route::get('/user/{id}', [\App\Http\Controllers\UserConfigController::class, 'getByUserId'])
            ->middleware('permission:user_config.show')
            ->name('user_config.show_by_user');
        Route::delete('/{id}', [\App\Http\Controllers\UserConfigController::class, 'destroy'])
            ->middleware('permission:user_config.delete')
            ->name('user_config.delete');
        Route::patch('/{id}', [\App\Http\Controllers\UserConfigController::class, 'update'])
            ->middleware('permission:user_config.edit')
            ->name('user_config.edit');
    });

    Route::prefix('user')->group(function () {
        Route::get('/', [\App\Http\Controllers\UserController::class, 'index'])
            ->middleware('permission:user.index')
            ->name('user.index');
    });

    Route::prefix('banks')->group(function () {
        Route::get('/', [\App\Http\Controllers\BankController::class, 'index'])
            ->middleware('permission:banks.index')
            ->name('banks.index');
        Route::post('/', [\App\Http\Controllers\BankController::class, 'store'])
            ->middleware('permission:banks.create')
            ->name('banks.create');
        Route::get('/{id}', [\App\Http\Controllers\BankController::class, 'show'])
            ->middleware('permission:banks.show')
            ->name('banks.show');
        Route::patch('/{id}', [\App\Http\Controllers\BankController::class, 'update'])
            ->middleware('permission:banks.update')
            ->name('banks.update');
        Route::delete('/{id}', [\App\Http\Controllers\BankController::class, 'destroy'])
            ->middleware('permission:banks.delete')
            ->name('banks.delete');
    });

    Route::get('/user-permissions', [\App\Http\Controllers\UserController::class, 'userPermissions']);
    Route::get('/log-activity', [\App\Http\Controllers\ActivityLogController::class, 'index']);

});




