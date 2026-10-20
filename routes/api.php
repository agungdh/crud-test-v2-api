<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BerkasUploadController;
use App\Http\Controllers\DiklatController;
use App\Http\Controllers\JenisPelatihanController;
use App\Http\Controllers\PegawaiController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');

Route::middleware('auth:sanctum')->group(function (): void {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    Route::apiResource('berkas-upload', BerkasUploadController::class)->only(['store', 'destroy']);

    Route::apiResource('pegawai', PegawaiController::class)->except('update');
    Route::put('pegawai/{pegawai}', [PegawaiController::class, 'update'])->name('pegawai.update');

    Route::apiResource('diklat', DiklatController::class)->except('update');
    Route::put('diklat/{diklat}', [DiklatController::class, 'update'])->name('diklat.update');

    Route::apiResource('jenis-pelatihan', JenisPelatihanController::class)->except('update');
    Route::put('jenis-pelatihan/{jenis_pelatihan}', [JenisPelatihanController::class, 'update'])->name('jenis-pelatihan.update');
});
