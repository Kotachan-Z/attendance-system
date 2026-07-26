<?php

use App\Http\Controllers\Admin\TeacherController;
use App\Http\Controllers\AttendanceRecordController;
use App\Http\Controllers\AttendanceSessionController;
use App\Http\Controllers\Auth\StudentAuthController;
use App\Http\Controllers\ClassGroupController;
use App\Http\Controllers\ClassScheduleController;
use App\Http\Controllers\CourseController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DetectionLogController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Student\PasswordController as StudentPasswordController;
use App\Http\Controllers\Student\PortalController;
use App\Http\Controllers\StudentController;
use Illuminate\Support\Facades\Route;

// 未ログインはログイン画面へ
Route::middleware(['auth', 'verified'])->group(function () {

    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    // 複数学生にまとめて組（クラス）を登録（静的パスなので resource より前に定義）
    Route::post('students/bulk-class', [StudentController::class, 'bulkClass'])
         ->name('students.bulkClass');
    Route::resource('students', StudentController::class)
         ->only(['index', 'create', 'store', 'show', 'edit', 'update', 'destroy']);
    Route::delete('students/{student}/faces/{face}', [StudentController::class, 'destroyFace'])
         ->name('students.faces.destroy');

    // 管理者（admin）のみ: 授業・スケジュール・セッションの管理
    // 注: 静的パス（sessions/create など）は動的パス（sessions/{session}）より
    //     先に登録する必要があるため、閲覧用ルートより前に定義する。
    Route::middleware('admin')->group(function () {
        Route::resource('courses', CourseController::class)
             ->only(['create', 'store', 'edit', 'update', 'destroy']);

        Route::post('schedules/slot', [ClassScheduleController::class, 'storeSlot'])
             ->name('schedules.slot');
        Route::resource('schedules', ClassScheduleController::class)
             ->only(['index', 'create', 'store', 'destroy']);
        Route::post('schedules/{schedule}/generate', [ClassScheduleController::class, 'generate'])
             ->name('schedules.generate');

        Route::get('sessions/create', [AttendanceSessionController::class, 'create'])->name('sessions.create');
        Route::post('sessions', [AttendanceSessionController::class, 'store'])->name('sessions.store');
        Route::patch('sessions/{session}/end', [AttendanceSessionController::class, 'end'])->name('sessions.end');

        // 組（クラス）の選択肢マスタの追加・削除
        Route::post('class-groups', [ClassGroupController::class, 'store'])->name('class-groups.store');
        Route::delete('class-groups/{classGroup}', [ClassGroupController::class, 'destroy'])->name('class-groups.destroy');

        // なりすまし疑い・識別不能の検出ログ
        Route::get('detections', [DetectionLogController::class, 'index'])->name('detections.index');

        // 教職員アカウント管理
        Route::get('admin/teachers', [TeacherController::class, 'index'])->name('admin.teachers.index');
        Route::post('admin/teachers', [TeacherController::class, 'store'])->name('admin.teachers.store');
        Route::put('admin/teachers/{user}/password', [TeacherController::class, 'resetPassword'])->name('admin.teachers.password');
        Route::delete('admin/teachers/{user}', [TeacherController::class, 'destroy'])->name('admin.teachers.destroy');
    });

    // 閲覧は全ユーザー可
    Route::resource('courses', CourseController::class)->only(['index']);
    Route::get('courses/{course}/attendance', [CourseController::class, 'attendance'])
         ->name('courses.attendance');

    // 出欠の手動修正（管理者・先生どちらも可）
    Route::post('sessions/{session}/students/{student}/status', [AttendanceRecordController::class, 'updateStatus'])
         ->name('records.updateStatus');
    Route::get('sessions', [AttendanceSessionController::class, 'index'])->name('sessions.index');
    Route::get('sessions/{session}', [AttendanceSessionController::class, 'show'])->name('sessions.show');

    // Breeze プロフィール
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// ─────────────────────────────────────────────────────────────
// 生徒エリア（学籍番号 + パスワードでログイン。閲覧のみ）
// ─────────────────────────────────────────────────────────────
Route::prefix('student')->group(function () {
    Route::middleware('guest:student')->group(function () {
        Route::get('login', [StudentAuthController::class, 'create'])->name('student.login');
        Route::post('login', [StudentAuthController::class, 'store']);
    });

    Route::middleware('auth:student')->group(function () {
        Route::get('/', [PortalController::class, 'index'])->name('student.dashboard');
        Route::get('password', [StudentPasswordController::class, 'edit'])->name('student.password.edit');
        Route::put('password', [StudentPasswordController::class, 'update'])->name('student.password.update');
        Route::post('logout', [StudentAuthController::class, 'destroy'])->name('student.logout');
    });
});

require __DIR__.'/auth.php';
