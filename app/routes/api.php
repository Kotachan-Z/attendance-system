<?php

use App\Http\Controllers\Api\AttendanceController;
use App\Http\Controllers\Api\AttendanceSessionController;
use App\Http\Controllers\Api\DetectionController;
use App\Http\Controllers\Api\StudentController;
use App\Http\Middleware\VerifyCameraToken;
use Illuminate\Support\Facades\Route;

Route::middleware(VerifyCameraToken::class)->group(function () {
    Route::get('/students', [StudentController::class, 'index']);
    Route::get('/sessions/active', [AttendanceSessionController::class, 'active']);
    Route::post('/attendance', [AttendanceController::class, 'store']);
    Route::post('/detections', [DetectionController::class, 'store']);
});
