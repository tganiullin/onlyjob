<?php

use App\Http\Controllers\InterviewAudioController;
use App\Http\Controllers\InterviewPdfController;
use App\Http\Controllers\PublicInterviewRunController;
use App\Http\Controllers\PublicPositionInterviewController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', 'https://aya.ru');
// TODO: Вынести роуты в api.php и фронт крутить как отдельный сервис, использовать единый стандарт ответов API JSON:API specification или JSEND

Route::middleware(['web', 'auth'])->group(function (): void {
    Route::get('admin/interview-audio/{interviewQuestion}', [InterviewAudioController::class, 'stream'])
        ->name('interview-audio.stream');
    Route::get('admin/interviews/{record}/export-pdf', InterviewPdfController::class)
        ->name('interviews.export-pdf');
});

Route::prefix('public/positions')->name('public-positions.')->group(function (): void {
    Route::get('{token}', [PublicPositionInterviewController::class, 'show'])->name('show');
    Route::post('{token}/start', [PublicPositionInterviewController::class, 'start'])
        ->middleware('throttle:public-position-start')
        ->name('start');
    Route::get('{token}/confirmations/{statusToken}/status', [PublicPositionInterviewController::class, 'confirmationStatus'])
        ->middleware('throttle:public-interview-confirmation-status')
        ->name('confirmation-status');
});

Route::prefix('public/interviews')->name('public-interviews.')->group(function (): void {
    Route::get('{interview}', [PublicInterviewRunController::class, 'run'])->name('run');
    Route::post('{interview}/transcribe', [PublicInterviewRunController::class, 'transcribe'])
        ->middleware('throttle:public-interview-transcribe')
        ->name('transcribe');
    Route::get('{interview}/transcriptions/{key}', [PublicInterviewRunController::class, 'transcriptionStatus'])
        ->middleware('throttle:public-interview-transcription-status')
        ->name('transcription-status');
    Route::post('{interview}/questions/{interviewQuestion}', [PublicInterviewRunController::class, 'answer'])
        ->middleware('throttle:public-interview-answer')
        ->name('questions.answer');
    Route::post('{interview}/questions/{interviewQuestion}/skip', [PublicInterviewRunController::class, 'skip'])
        ->middleware('throttle:public-interview-answer')
        ->name('questions.skip');
    Route::get('{interview}/follow-up-status/{key}', [PublicInterviewRunController::class, 'followUpStatus'])
        ->middleware('throttle:public-interview-follow-up-status')
        ->name('follow-up-status');
    Route::post('{interview}/feedback', [PublicInterviewRunController::class, 'feedback'])
        ->middleware('throttle:public-interview-answer')
        ->name('feedback');
    Route::post('{interview}/custom-question', [PublicInterviewRunController::class, 'customQuestion'])
        ->middleware('throttle:public-interview-answer')
        ->name('custom-question');
    Route::post('{interview}/integrity-signal', [PublicInterviewRunController::class, 'integritySignal'])
        ->middleware('throttle:public-interview-integrity-signal')
        ->name('integrity-signal');
});
