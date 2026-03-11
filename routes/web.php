<?php

use App\Http\Controllers\PublicInterviewRunController;
use App\Http\Controllers\PublicPositionInterviewController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});
// TODO: Вынести роуты в api.php и фронт крутить как отдельный сервис, использовать единый стандарт ответов API JSON:API specification или JSEND

Route::prefix('public/positions')->name('public-positions.')->group(function (): void {
    Route::get('{token}', [PublicPositionInterviewController::class, 'show'])->name('show');
    Route::post('{token}/start', [PublicPositionInterviewController::class, 'start'])
        ->middleware('throttle:public-position-start')
        ->name('start');
});

Route::prefix('public/interviews')->name('public-interviews.')->group(function (): void {
    Route::get('{interview}', [PublicInterviewRunController::class, 'run'])->name('run');
    Route::post('{interview}/transcribe', [PublicInterviewRunController::class, 'transcribe'])
        ->middleware('throttle:public-interview-transcribe')
        ->name('transcribe');
    Route::post('{interview}/questions/{interviewQuestion}', [PublicInterviewRunController::class, 'answer'])
        ->middleware('throttle:public-interview-answer')
        ->name('questions.answer');
});
