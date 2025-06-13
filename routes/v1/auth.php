<?php

use App\Http\Controllers\Auth\AuthController;
use Illuminate\Support\Facades\Route;


Route::group(['prefix' => '/v1/auth'], function () {
    Route::post('validate', [AuthController::class, 'validate2FA']);
    Route::post('save-fcm-token', [AuthController::class, 'saveFcmToken']);
    Route::post('login', [AuthController::class, 'login']);
    Route::post('register-user', [AuthController::class, 'register']);
    Route::post('send-otp', [AuthController::class, 'sendOtp']);
    Route::post('resend-otp', [AuthController::class, 'resendOtp']);
    Route::post('verify-otp', [AuthController::class, 'verifyOtp']);
    Route::post('verify-email', [AuthController::class, 'verifyOtpForRegistration']);
    Route::post('reset-password', [AuthController::class, 'resetPassword']);

    Route::group(['middleware' => 'auth:sanctum'], function () {
    Route::get('logout', [AuthController::class, 'logout']);
    Route::post('change-password', [AuthController::class, 'changePassword']);
    Route::group(['prefix' => 'two-factor'], function () {
    Route::post('generate-key', [AuthController::class, 'generateKey']);
    Route::post('enable-two-factor', [AuthController::class, 'enableTwoFactor']);
    Route::post('disable-two-factor', [AuthController::class, 'disableTwoFactor']);
    });

    Route::get('user', [AuthController::class, 'user']);
    });
});