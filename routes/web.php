<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('login.docs');
});

Route::get('/login-docs', [\App\Http\Controllers\Auth\LoginDocsController::class, 'show'])->name('login.docs');
Route::post('/login-docs', [\App\Http\Controllers\Auth\LoginDocsController::class, 'login'])->name('login.docs.post');

Route::get('/logout-docs', [\App\Http\Controllers\Auth\LoginDocsController::class, 'logout'])->name('logout.docs');
