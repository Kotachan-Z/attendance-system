<?php

namespace App\Http\Controllers;

use App\Models\AttendanceSession;
use App\Models\Course;
use App\Services\AttendanceFinalizer;
use Illuminate\Http\Request;

class AttendanceSessionController extends Controller
{
    public function index()
    {
        $sessions = AttendanceSession::with('course')
                                     ->withCount('attendanceRecords')
                                     ->latest()
                                     ->paginate(20);
        return view('sessions.index', compact('sessions'));
    }

    public function create()
    {
        $courses = Course::orderBy('name')->get();
        return view('sessions.create', compact('courses'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'course_id'    => 'required|exists:courses,id',
            'session_date' => 'required|date',
        ]);

        AttendanceSession::create([
            'course_id'    => $validated['course_id'],
            'session_date' => $validated['session_date'],
            'started_at'   => now(),
        ]);

        return redirect()->route('sessions.index')->with('success', '授業セッションを開始しました。');
    }

    public function show(AttendanceSession $session)
    {
        $session->load('course');
        $records = $session->attendanceRecords()->with('student')->latest()->get();
        return view('sessions.show', compact('session', 'records'));
    }

    public function end(AttendanceSession $session, AttendanceFinalizer $finalizer)
    {
        $session->update(['ended_at' => now()]);

        // 履修学生のうち未検出の者を欠席として記録
        $absent = $finalizer->markAbsentees($session);

        $msg = 'セッションを終了しました。';
        if ($absent > 0) {
            $msg .= "（未出席の履修学生 {$absent}名 を欠席として記録）";
        }
        return redirect()->route('sessions.show', $session)->with('success', $msg);
    }

    public function edit(AttendanceSession $session) {}
    public function update(Request $request, AttendanceSession $session) {}
    public function destroy(AttendanceSession $session) {}
}
