<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\StudyPlanController;
use Illuminate\Support\Facades\Route;


Route::group(['prefix' => '/studyplan'], function () {
    Route::post('/uploadfile', [StudyPlanController::class, 'uploadfile']);
});