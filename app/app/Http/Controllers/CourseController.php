<?php

namespace App\Http\Controllers;

use App\Models\AttendanceRecord;
use App\Models\Course;
use App\Models\Student;
use App\Models\User;
use Illuminate\Http\Request;

class CourseController extends Controller
{
    public function index()
    {
        $courses = Course::withCount(['attendanceSessions', 'students'])->latest()->get();
        return view('courses.index', compact('courses'));
    }

    /** 履修登録（学生の割り当て）＋担当教員の設定画面 */
    public function edit(Course $course)
    {
        $course->load(['students', 'teachers']);
        $students    = Student::active()->orderBy('class_name')->orderBy('name')->get();
        $enrolledIds = $course->students->pluck('id')->all();

        // 担当教員に割り当て可能なのは教職員（admin / teacher）アカウント
        $teachers    = User::orderBy('name')->get();
        $teacherIds  = $course->teachers->pluck('id')->all();

        return view('courses.edit', compact('course', 'students', 'enrolledIds', 'teachers', 'teacherIds'));
    }

    public function update(Request $request, Course $course)
    {
        $validated = $request->validate([
            'student_ids'   => 'nullable|array',
            'student_ids.*' => 'integer|exists:students,id',
            'teacher_ids'   => 'nullable|array',
            'teacher_ids.*' => 'integer|exists:users,id',
        ]);

        $course->students()->sync($validated['student_ids'] ?? []);
        $course->teachers()->sync($validated['teacher_ids'] ?? []);

        return redirect()->route('courses.index')
            ->with('success', "「{$course->name}」の履修登録・担当教員を更新しました。");
    }

    public function create()
    {
        return view('courses.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'                   => 'required|string|max:255',
            'description'            => 'nullable|string|max:1000',
            'late_threshold_minutes' => 'nullable|integer|min:0|max:120',
        ]);
        $validated['late_threshold_minutes'] = $validated['late_threshold_minutes'] ?? 20;

        Course::create($validated);

        return redirect()->route('courses.index')->with('success', '授業を作成しました。');
    }

    public function destroy(Course $course)
    {
        $course->delete();
        return redirect()->route('courses.index')->with('success', '授業を削除しました。');
    }

    /**
     * 出欠一覧表（マトリクス）。
     * 行 = 履修学生、列 = 授業があった日（セッション）、セル = 出席/遅刻/欠席。
     * 授業がない日は列に現れない（= 表示しない）。
     */
    public function attendance(Course $course)
    {
        // 履修学生（在籍中のみ・組→氏名順）
        $students = $course->students()
            ->active()
            ->orderByRaw("CASE WHEN class_name IS NULL OR class_name = '' THEN 1 ELSE 0 END")
            ->orderBy('class_name')
            ->orderBy('name')
            ->get();

        // 授業があった日（本日以前のセッションのみ）= 列
        $sessions = $course->attendanceSessions()
            ->whereDate('session_date', '<=', today())
            ->orderBy('session_date')
            ->orderBy('scheduled_start_at')
            ->get();

        // [session_id][student_id] => ['status','note','manual'] の早引きマップ
        $records = AttendanceRecord::whereIn('attendance_session_id', $sessions->pluck('id'))
            ->get();
        $statusMap = [];
        foreach ($records as $r) {
            $statusMap[$r->attendance_session_id][$r->student_id] = [
                'status' => $r->status,
                'note'   => $r->note,
                'manual' => ! is_null($r->manually_updated_at),
            ];
        }

        // 学生ごとの集計（出席/遅刻/欠席の回数）
        $summary = [];
        foreach ($students as $s) {
            $counts = ['present' => 0, 'late' => 0, 'absent' => 0];
            foreach ($sessions as $sess) {
                $st = $statusMap[$sess->id][$s->id]['status'] ?? null;
                if ($st && isset($counts[$st])) {
                    $counts[$st]++;
                }
            }
            $summary[$s->id] = $counts;
        }

        return view('courses.attendance', compact('course', 'students', 'sessions', 'statusMap', 'summary'));
    }
}
