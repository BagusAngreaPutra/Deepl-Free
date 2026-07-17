<?php

use App\Http\Controllers\TranslatorController;
use Illuminate\Support\Facades\Route;

Route::get('/', [TranslatorController::class, 'index'])->name('home');
Route::get('/brand-logo', fn () => response()->file(
    resource_path('assets/logo JDS tanpa company backgroun putih.png'),
    ['Cache-Control' => 'public, max-age=86400']
))->name('brand.logo');
Route::get('/health', [TranslatorController::class, 'health'])->name('health');
Route::post('/translate', [TranslatorController::class, 'translate'])->name('translate');
Route::post('/translate-pdf', [TranslatorController::class, 'translatePdf'])->name('translate.pdf');
Route::post('/ocr-translate', [TranslatorController::class, 'ocrTranslate'])->name('ocr.translate');
