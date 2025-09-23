<?php

use App\Http\Controllers\Api\SeccionesController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\ComponentRegistryController;
use App\Http\Controllers\PagePublicController;
use App\Http\Controllers\S360\Api\S360ApiController;
use App\Http\Controllers\ThemePublicController;
use App\Http\Controllers\Volante\EnrollmentController as VolanteEnrollmentController;
use App\Http\Controllers\Volante\GroupController as VolanteGroupController;
use App\Http\Controllers\Volante\PaymentController as VolantePaymentController;
use App\Http\Controllers\Volante\ReportController as VolanteReportController;
use App\Http\Controllers\Volante\SiteController as VolanteSiteController;
use Illuminate\Support\Facades\Route;

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

Route::prefix('bienestar/volante')
    ->middleware(['auth:sanctum', 'vol.audit'])
    ->group(function () {
        Route::middleware('permission:vol.sites.manage')->group(function () {
            Route::get('sites', [VolanteSiteController::class, 'index']);
            Route::post('sites', [VolanteSiteController::class, 'store']);
            Route::put('sites/{site}', [VolanteSiteController::class, 'update']);
            Route::delete('sites/{site}', [VolanteSiteController::class, 'destroy']);
        });

        Route::middleware('permission:vol.groups.view|vol.groups.manage')->group(function () {
            Route::get('groups', [VolanteGroupController::class, 'index']);
            Route::get('groups/{group}', [VolanteGroupController::class, 'show']);
        });

        Route::middleware('permission:vol.groups.manage')->group(function () {
            Route::post('groups', [VolanteGroupController::class, 'store']);
            Route::put('groups/{group}', [VolanteGroupController::class, 'update']);
            Route::delete('groups/{group}', [VolanteGroupController::class, 'destroy']);
            Route::post('groups/{group}/publish', [VolanteGroupController::class, 'publish']);
            Route::post('groups/{group}/close', [VolanteGroupController::class, 'close']);
            Route::get('payments', [VolantePaymentController::class, 'index']);
            Route::post('payments', [VolantePaymentController::class, 'store']);
        });

        Route::middleware('permission:vol.enrollments.manage')->group(function () {
            Route::get('groups/{group}/enrollments', [VolanteEnrollmentController::class, 'index']);
            Route::post('groups/{group}/enrollments', [VolanteEnrollmentController::class, 'store']);
            Route::delete('enrollments/{enrollment}', [VolanteEnrollmentController::class, 'destroy']);
        });

        Route::middleware('permission:vol.reports.view')->group(function () {
            Route::get('reports/monthly', [VolanteReportController::class, 'monthly']);
            Route::get('reports/quarterly', [VolanteReportController::class, 'quarterly']);
            Route::get('reports/availability', [VolanteReportController::class, 'availability']);
        });
    });