<?php

use App\Http\Controllers\TosController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| TOS / Exam Generation Routes
|--------------------------------------------------------------------------
| IMPORTANT: static routes like /tos/chat and /tos/create must be defined
| BEFORE the wildcard /tos/{tos} route. Laravel matches top-to-bottom, and
| {tos} will otherwise swallow /tos/chat (treating "chat" as a TOS id and
| throwing a 404 via failed route-model-binding).
*/

Route::middleware(['web'])->group(function () {
    Route::get('/tos/create', [TosController::class, 'create'])->name('tos.create');
    Route::get('/tos/chat', function () {
        return view('tos.chat');
    })->name('tos.chat');

    Route::post('/tos', [TosController::class, 'classifyAndBuildTos'])->name('tos.store');

    Route::get('/tos/history', [TosController::class, 'history'])->name('tos.history');

    // Wildcard route — must come AFTER the static routes above.
    Route::get('/tos/{tos}', [TosController::class, 'show'])->name('tos.show');

    Route::post('/tos/{tos}/generate-exam', [TosController::class, 'generateExam'])->name('tos.generate-exam');
    Route::post('/tos/{tos}/lessons/{lesson}/generate-exam', [TosController::class, 'generateExamForLesson'])->name('tos.generate-lesson-exam');
});