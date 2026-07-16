<?php

use App\Http\Controllers\TranslatorController;
use Illuminate\Support\Facades\Route;

Route::get('/', [TranslatorController::class, 'index'])->name('home');
Route::get('/health', [TranslatorController::class, 'health'])->name('health');
Route::post('/translate', [TranslatorController::class, 'translate'])->name('translate');
Route::post('/ocr-translate', [TranslatorController::class, 'ocrTranslate'])->name('ocr.translate');
