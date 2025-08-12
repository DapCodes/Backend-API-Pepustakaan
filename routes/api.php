<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::post('/register', [\App\Http\Controllers\Api\AuthController::class, 'register']);
Route::post('/login', [\App\Http\Controllers\Api\AuthController::class, 'login']);

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [\App\Http\Controllers\Api\AuthController::class, 'logout']);

    Route::resource('/posts', \App\Http\Controllers\Api\PostController::class)
        ->except(['create', 'edit']);

    Route::resource('/kategoris', \App\Http\Controllers\Api\KategoriController::class)
        ->except(['create', 'edit']);

    Route::resource('/bukus', \App\Http\Controllers\Api\BukuController::class)
        ->except(['create', 'edit']);

    Route::apiResource('peminjaman', \App\Http\Controllers\Api\PeminjamanController::class);
    Route::apiResource('pengembalian', \App\Http\Controllers\Api\PengembalianController::class);
    Route::get('peminjaman-belum-kembali', [\App\Http\Controllers\Api\PengembalianController::class, 'getPeminjamanBelumKembali']);
    Route::get('peminjaman-terlambat', [\App\Http\Controllers\Api\PengembalianController::class, 'getPeminjamanTerlambat']);
});
