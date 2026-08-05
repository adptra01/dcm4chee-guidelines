<?php

use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\PatientController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\WorklistController;
use Illuminate\Support\Facades\Route;

// MS3: RIS API — patient, order, worklist (MWL source). File ini di-mount di /api.
Route::get('patients', [PatientController::class, 'index']);
Route::post('patients', [PatientController::class, 'store']);

Route::get('orders', [OrderController::class, 'index']);
Route::post('orders', [OrderController::class, 'store']);

Route::get('worklist', [WorklistController::class, 'index']);

// MS5: Reporting — laporan radiologi per order
Route::get('reports', [ReportController::class, 'index']);
Route::post('reports', [ReportController::class, 'store']);
