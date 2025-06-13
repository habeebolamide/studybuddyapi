<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\QuizController;
use App\Http\Controllers\StudyPlanController;
use Illuminate\Support\Facades\Route;


Route::group(['prefix' => '/studyplan'], function () {
    Route::post('/uploadfile', [StudyPlanController::class, 'uploadfile']);
    Route::get('/get_all_study_notes', [StudyPlanController::class, 'getAll']);
    Route::get('/get_simplified_notes/{id}', [StudyPlanController::class, 'getSimplifiedNotes']);

    Route::get('/get_quiz/{id}', [QuizController::class, 'getQuiz']);
    Route::get('/get_allquiz', [QuizController::class, 'getallQuiz']);
    Route::post('/generate_quiz/{id}', [QuizController::class, 'generateQuizQuestion']);
    Route::post('/submit_quiz', [QuizController::class, 'submitQuiz']);

});