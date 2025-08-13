<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ClaimController;
use App\Http\Middleware\BasicAuth;

Route::middleware(BasicAuth::class)->group(function () {
    Route::get('/', [ClaimController::class, 'create']);
    Route::get('/claims/new', [ClaimController::class, 'create'])->name('claims.create');
    Route::post('/claims',     [ClaimController::class, 'store'])->name('claims.store');
});
