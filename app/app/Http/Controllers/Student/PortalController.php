<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\ClassSchedule;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class PortalController extends Controller
{
    /** 生徒マイページ: 自分の出欠表 + 自分の時間割 */
    public function index()
    {
        $student = Auth::guard('student')->user();

        // 履修している授業
        $courses   = $student->courses()->with('classSchedules')->orderBy('name')->get();
        $courseIds = $courses->pluck('id');

        // 授業があった日（本日まで）のセッションを授業ごとにまとめる
        $sessionsByCourse = AttendanceSession::whereIn('course_id', $courseIds)
            ->whereDate('session_date', '<=', today())
            ->orderBy('session_date')
            ->get()
            ->groupBy('course_id');

        // 自分の出欠記録（session_id => record）
        $records = AttendanceRecord::where('student_id', $student->id)
            ->whereIn('attendance_session_id', $sessionsByCourse->flatten()->pluck('id'))
            ->get()
            ->keyBy('attendance_session_id');

        // 授業ごとに行（日付＋ステータス）と集計を組み立てる
        $attendance = [];
        foreach ($courses as $course) {
            $sessions = $sessionsByCourse->get($course->id, collect());
            $rows = [];
            $counts = ['present' => 0, 'late' => 0, 'absent' => 0];
            foreach ($sessions as $sess) {
                $rec = $records->get($sess->id);
                $status = $rec->status ?? null;
                if ($status && isset($counts[$status])) {
                    $counts[$status]++;
                }
                $rows[] = [
                    'date'   => $sess->session_date,
                    'status' => $status,
                    'note'   => $rec->note ?? null,
                    'manual' => $rec && ! is_null($rec->manually_updated_at),
                ];
            }
            $attendance[] = [
                'course'  => $course,
                'rows'    => $rows,
                'counts'  => $counts,
                'total'   => count($rows),
            ];
        }

        // 時間割グリッド（自分の履修授業の weekly スケジュールのみ）
        $periods = config('timetable.periods');
        $days    = config('timetable.days');
        $schedules = ClassSchedule::with('course')->whereIn('course_id', $courseIds)->get();

        $grid  = [];
        $other = [];
        foreach ($schedules as $s) {
            $startHm = Str::substr($s->start_time, 0, 5);
            $fits = $s->type === 'weekly'
                && in_array($s->day_of_week, $days, true)
                && collect($periods)->contains(fn ($p) => $p['start'] === $startHm);
            if ($fits) {
                $grid[$s->day_of_week . '|' . $startHm] = $s;
            } else {
                $other[] = $s;
            }
        }

        $dowLabels = ClassSchedule::DOW_LABELS;

        return view('student.dashboard', compact(
            'student', 'attendance', 'periods', 'days', 'grid', 'other', 'dowLabels'
        ));
    }
}
