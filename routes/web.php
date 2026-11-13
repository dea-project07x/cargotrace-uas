<?php

use App\Http\Controllers\Web\Auth\LoginController;
use App\Http\Controllers\Web\ShipmentController;
use App\Http\Controllers\Web\TrackingMapController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/shipments');

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:10,1');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

    Route::get('/shipments', [ShipmentController::class, 'index'])->name('shipments.index');
    Route::get('/shipments/{shipment}', [ShipmentController::class, 'show'])->name('shipments.show');

    Route::get('/tracking', [TrackingMapController::class, 'index'])->name('tracking.index');
    Route::get('/tracking/data', [TrackingMapController::class, 'data'])->name('tracking.data');
});
