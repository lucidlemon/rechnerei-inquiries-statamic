<?php

use Illuminate\Support\Facades\Route;
use Rechnerei\Inquiries\Http\Controllers\SettingsController;

Route::name('rechnerei-inquiries.')
    ->prefix('rechnerei-inquiries')
    ->middleware('can:configure rechnerei inquiries')
    ->group(function () {
        Route::get('/', [SettingsController::class, 'index'])->name('index');
        Route::post('/', [SettingsController::class, 'update'])->name('update');
        Route::post('/test', [SettingsController::class, 'test'])->name('test');
    });
