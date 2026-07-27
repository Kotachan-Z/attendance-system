<?php

namespace App\Http\Controllers;

use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\Student;

class DashboardController extends Controller
{
    public function index()
    {
        $todaySessions = AttendanceSession::with(['course', 'attendanceRecords'])
                                          ->whereDate('session_date', today())
                                          ->latest()
                                          ->get();

        // 「進行中」= 未終了、かつ（手動開始 or 予定開始〜予定終了の時間内）。
        //   未来の時間割セッションは進行中に数えない。
        $now = now();
        $activeSessions = AttendanceSession::whereNull('ended_at')
                                           ->where(function ($q) use ($now) {
                                               $q->whereNull('scheduled_start_at')
                                                 ->orWhere(function ($q2) use ($now) {
                                                     $q2->where('scheduled_start_at', '<=', $now)
                                                        ->where('scheduled_end_at', '>=', $now);
                                                 });
                                           })
                                           ->with('course')
                                           ->withCount('attendanceRecords')
                                           ->get();

        // 在籍学生のみ（退学者は名簿・API と同様に集計から除外）
        $totalStudents  = Student::active()->count();
        $todayPresent   = AttendanceRecord::whereHas('attendanceSession', fn($q) =>
                              $q->whereDate('session_date', today())
                          )->distinct('student_id')->count('student_id');

        return view('dashboard', compact(
            'todaySessions',
            'activeSessions',
            'totalStudents',
            'todayPresent'
        ));
    }
}
