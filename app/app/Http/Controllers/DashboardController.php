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

        $activeSessions = AttendanceSession::whereNull('ended_at')
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
