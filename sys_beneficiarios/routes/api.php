<?php

use App\Http\Controllers\Api\SeccionesController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\ComponentRegistryController;
use App\Http\Controllers\PagePublicController;
use App\Http\Controllers\S360\Api\S360ApiController;
use App\Http\Controllers\ThemePublicController;
use Illuminate\\Support\\Facades\\Route;

Route::middleware(['etag', 'throttle:public'])->get('/health', function () {
    return response()->json(['status' => 'ok']);
});

Route::middleware(['etag', 'throttle:public'])->get('/pages/{slug}', PagePublicController::class);
Route::middleware(['etag', 'throttle:public'])->get('/components/registry', ComponentRegistryController::class);
Route::middleware(['etag', 'throttle:public'])->get('/themes/current', ThemePublicController::class);

Route::prefix('auth')->group(function () {
    Route::post('login', LoginController::class);
    Route::post('logout', LogoutController::class)->middleware('auth:sanctum');
});

Route::middleware('throttle:30,1')->get('/secciones/{seccional}', [SeccionesController::class, 'show']);

Route::prefix('s360')
    ->middleware(['auth:sanctum', 'throttle:30,1'])
    ->group(function () {
        Route::get('psico/pacientes', [S360ApiController::class, 'psicoPacientes']);
        Route::get('psico/sesiones/{beneficiario}', [S360ApiController::class, 'psicoHistorial']);
        Route::get('enc360/dash', [S360ApiController::class, 'enc360Dash']);
        Route::get('bienestar/dash', [S360ApiController::class, 'bienestarDash']);
    });











