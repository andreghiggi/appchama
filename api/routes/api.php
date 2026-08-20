<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CityController;
use App\Http\Controllers\Api\DriverController;
use App\Http\Controllers\Api\RideController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware(['tenant'])->group(function () {
    Route::post('/auth/otp/send', [AuthController::class, 'sendOtp']);
    Route::post('/auth/otp/verify', [AuthController::class, 'verifyOtp']);
    Route::get('/cities', [CityController::class, 'index']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/me', fn (Request $request) => $request->user()->load('driver.vehicles'));

        Route::get('/rides', [RideController::class, 'index']);
        Route::post('/rides', [RideController::class, 'store']);
        Route::get('/rides/{ride}', [RideController::class, 'show']);
        Route::post('/rides/{ride}/accept', [RideController::class, 'accept']);
        Route::post('/rides/{ride}/decline', [RideController::class, 'decline']);
        Route::post('/rides/{ride}/arrive', [RideController::class, 'arrive']);
        Route::post('/rides/{ride}/start', [RideController::class, 'start']);
        Route::post('/rides/{ride}/complete', [RideController::class, 'complete']);
        Route::post('/rides/{ride}/cancel', [RideController::class, 'cancel']);
        Route::post('/rides/{ride}/rate', [RideController::class, 'rate']);

        Route::post('/drivers/location', [DriverController::class, 'updateLocation']);
        Route::post('/drivers/online', [DriverController::class, 'goOnline']);
        Route::post('/drivers/offline', [DriverController::class, 'goOffline']);
        Route::get('/drivers/dashboard', [DriverController::class, 'dashboard']);
    });
});
