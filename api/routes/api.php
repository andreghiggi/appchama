<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CityController;
use App\Http\Controllers\Api\DriverController;
use App\Http\Controllers\Api\DriverWalletController;
use App\Http\Controllers\Api\GeocodeController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\RideController;
use App\Http\Controllers\Api\RidePaymentController;
use App\Http\Controllers\Api\SavedAddressController;
use App\Http\Controllers\Webhooks\AsaasWebhookController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware(['tenant'])->group(function () {
    Route::post('/auth/otp/send', [AuthController::class, 'sendOtp']);
    Route::post('/auth/otp/verify', [AuthController::class, 'verifyOtp']);
    Route::post('/auth/driver/login', [AuthController::class, 'driverLogin']);
    Route::post('/auth/driver/register', [AuthController::class, 'driverRegister']);
    Route::get('/cities', [CityController::class, 'index']);
    Route::get('/cities/nearby', [CityController::class, 'nearby']);
    Route::get('/cities/nearest', [CityController::class, 'nearest']);
    Route::get('/geocode/reverse', [GeocodeController::class, 'reverse']);
    Route::get('/geocode/search', [GeocodeController::class, 'search']);

    Route::post('/webhooks/asaas', [AsaasWebhookController::class, 'handle']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/me', fn (Request $request) => $request->user()->load('driver.vehicles'));
        Route::put('/profile', [ProfileController::class, 'update']);

        Route::get('/rides', [RideController::class, 'index']);
        Route::get('/rides/estimate', [RideController::class, 'estimate']);
        Route::get('/rides/active', [RideController::class, 'active']);
        Route::post('/rides', [RideController::class, 'store']);
        Route::get('/rides/{ride}', [RideController::class, 'show']);
        Route::post('/rides/{ride}/accept', [RideController::class, 'accept']);
        Route::post('/rides/{ride}/decline', [RideController::class, 'decline']);
        Route::post('/rides/{ride}/arrive', [RideController::class, 'arrive']);
        Route::post('/rides/{ride}/start', [RideController::class, 'start']);
        Route::post('/rides/{ride}/complete', [RideController::class, 'complete']);
        Route::post('/rides/{ride}/cancel', [RideController::class, 'cancel']);
        Route::post('/rides/{ride}/rate', [RideController::class, 'rate']);
        Route::get('/rides/{ride}/payment', [RidePaymentController::class, 'show']);
        Route::post('/rides/{ride}/pay', [RidePaymentController::class, 'pay']);
        Route::post('/rides/{ride}/payment/cancel', [RidePaymentController::class, 'cancel']);
        Route::post('/rides/{ride}/payment-method', [RidePaymentController::class, 'chooseMethod']);
        Route::post('/rides/{ride}/confirm-direct-payment', [RidePaymentController::class, 'confirmDirect']);

        Route::post('/drivers/location', [DriverController::class, 'updateLocation']);
        Route::post('/drivers/registration-code/confirm', [DriverController::class, 'confirmRegistrationCode']);
        Route::post('/drivers/online', [DriverController::class, 'goOnline']);
        Route::post('/drivers/offline', [DriverController::class, 'goOffline']);
        Route::get('/drivers/dashboard', [DriverController::class, 'dashboard']);
        Route::get('/drivers/offers', [DriverController::class, 'pendingOffer']);
        Route::get('/drivers/wallet', [DriverWalletController::class, 'show']);
        Route::put('/drivers/payout-account', [DriverWalletController::class, 'updatePayoutAccount']);
        Route::post('/drivers/wallet/recharges', [DriverWalletController::class, 'recharge']);
        Route::post('/drivers/withdrawals', [DriverWalletController::class, 'requestWithdrawal']);
        Route::get('/drivers/withdrawals', [DriverWalletController::class, 'listWithdrawals']);
        Route::get('/drivers/earnings-report', [DriverWalletController::class, 'earningsReport']);

        Route::get('/saved-addresses', [SavedAddressController::class, 'index']);
        Route::post('/saved-addresses', [SavedAddressController::class, 'store']);
        Route::delete('/saved-addresses/{savedAddress}', [SavedAddressController::class, 'destroy']);
    });
});
