<?php

use App\Http\Controllers\Api\AttendanceController;
use App\Http\Controllers\Api\AttendanceSessionController;
use App\Http\Controllers\Api\DetectionController;
use App\Http\Controllers\Api\StudentController;
use App\Http\Middleware\VerifyCameraToken;
use Illuminate\Support\Facades\Route;

// throttle:120,1 → 1分あたり120リクエストまで（カメラ1台 + 30fps想定で十分な余裕）
Route::middleware([VerifyCameraToken::class, 'throttle:120,1'])->group(function () {
    Route::get('/students', [StudentController::class, 'index']);
    Route::get('/sessions/active', [AttendanceSessionController::class, 'active']);
    Route::post('/attendance', [AttendanceController::class, 'store']);
    Route::post('/detections', [DetectionController::class, 'store']);
});
