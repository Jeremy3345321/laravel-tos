<?php

use App\Http\Controllers\TosController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| TOS / Exam Generation Routes
|--------------------------------------------------------------------------
| Add these into your existing routes/web.php (don't overwrite the file —
| just merge these route definitions in, e.g. inside your teacher-facing
| route group / middleware).
*/

Route::middleware(['web'])->group(function () {
    Route::get('/tos/create', [TosController::class, 'create'])->name('tos.create');
    Route::post('/tos', [TosController::class, 'classifyAndBuildTos'])->name('tos.store');
    Route::get('/tos/{tos}', [TosController::class, 'show'])->name('tos.show');
    Route::post('/tos/{tos}/generate-exam', [TosController::class, 'generateExam'])->name('tos.generate-exam');
});
