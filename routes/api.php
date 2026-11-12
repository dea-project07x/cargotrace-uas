<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CarrierController;
use App\Http\Controllers\Api\ExceptionController;
use App\Http\Controllers\Api\LocationController;
use App\Http\Controllers\Api\ShipmentController;
use App\Http\Controllers\Api\ShipmentExceptionController;
use App\Http\Controllers\Api\ShipmentTrackingController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:20,1');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:20,1');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);

    Route::apiResource('shipments', ShipmentController::class);

    Route::get('shipments/{shipment}/tracking', [ShipmentTrackingController::class, 'index']);
    Route::post('shipments/{shipment}/tracking', [ShipmentTrackingController::class, 'store']);

    Route::get('shipments/{shipment}/exceptions', [ShipmentExceptionController::class, 'index']);
    Route::post('shipments/{shipment}/exceptions', [ShipmentExceptionController::class, 'store']);
    Route::patch('exceptions/{exception}', [ExceptionController::class, 'update']);

    Route::get('carriers', [CarrierController::class, 'index']);
    Route::post('carriers', [CarrierController::class, 'store']);
    Route::get('locations', [LocationController::class, 'index']);
    Route::post('locations', [LocationController::class, 'store']);
});
