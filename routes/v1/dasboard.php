<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\DashBoardController;
use Illuminate\Support\Facades\Route;


Route::group(['prefix' => '/dashboard'], function () {
    Route::get('/analytics', [DashBoardController::class, 'dashboardAnalytics']);
});