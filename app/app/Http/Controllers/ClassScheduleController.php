<?php

namespace App\Http\Controllers;

use App\Models\ClassSchedule;
use App\Models\Course;
use App\Services\SessionGenerator;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ClassScheduleController extends Controller
{
    /** 時間割グリッド画面 */
    public function index()
    {
        $periods = config('timetable.periods');
        $days    = config('timetable.days');
        $courses = Course::orderBy('name')->get();

        $schedules = ClassSchedule::with('course')->get();

        // グリッドのセルに入る週次スケジュールを "曜日|HH:MM" で引けるようにする
        $grid  = [];
        $other = [];
        foreach ($schedules as $s) {
            $startHm = Str::substr($s->start_time, 0, 5); // "HH:MM"
            $key     = $s->day_of_week . '|' . $startHm;

            $fitsCell = $s->type === 'weekly'
                && in_array($s->day_of_week, $days, true)
                && collect($periods)->contains(fn ($p) => $p['start'] === $startHm);

            if ($fitsCell) {
                $grid[$key] = $s;
            } else {
                $other[] = $s; // 単発・グリッド外の時間など
            }
        }

        // 学期（有効期間）の初期値
        $termFrom = now()->startOfMonth()->toDateString();
        $termTo   = now()->addMonths(3)->endOfMonth()->toDateString();

        $dowLabels = ClassSchedule::DOW_LABELS;

        return view('schedules.index', compact(
            'periods', 'days', 'courses', 'grid', 'other', 'termFrom', 'termTo', 'dowLabels'
        ));
    }

    /** グリッドのセルから授業を割り当てる */
    public function storeSlot(Request $request, SessionGenerator $generator)
    {
        $validated = $request->validate([
            'course_id'       => 'required|exists:courses,id',
            'day_of_week'     => 'required|integer|between:0,6',
            'start_time'      => 'required|date_format:H:i',
            'end_time'        => 'required|date_format:H:i|after:start_time',
            'effective_from'  => 'required|date',
            'effective_until' => 'required|date|after_or_equal:effective_from',
        ]);

        $schedule = ClassSchedule::create([
            'course_id'       => $validated['course_id'],
            'type'            => 'weekly',
            'day_of_week'     => $validated['day_of_week'],
            'start_time'      => $validated['start_time'],
            'end_time'        => $validated['end_time'],
            'effective_from'  => $validated['effective_from'],
            'effective_until' => $validated['effective_until'],
        ]);

        $count = $generator->generateForSchedule($schedule);

        return redirect()->route('schedules.index')
            ->with('success', "授業を割り当て、{$count} 件のセッションを生成しました。");
    }

    /** 単発・詳細登録フォーム */
    public function create()
    {
        $courses = Course::orderBy('name')->get();
        return view('schedules.create', compact('courses'));
    }

    public function store(Request $request, SessionGenerator $generator)
    {
        $validated = $request->validate([
            'course_id'  => 'required|exists:courses,id',
            'type'       => ['required', Rule::in(['weekly', 'onetime'])],
            'start_time' => 'required|date_format:H:i',
            'end_time'   => 'required|date_format:H:i|after:start_time',
            'day_of_week'     => 'required_if:type,weekly|nullable|integer|between:0,6',
            'effective_from'  => 'required_if:type,weekly|nullable|date',
            'effective_until' => 'required_if:type,weekly|nullable|date|after_or_equal:effective_from',
            'specific_date'   => 'required_if:type,onetime|nullable|date',
        ]);

        if ($validated['type'] === 'weekly') {
            $validated['specific_date'] = null;
        } else {
            $validated['day_of_week']     = null;
            $validated['effective_from']  = null;
            $validated['effective_until'] = null;
        }

        $schedule = ClassSchedule::create($validated);
        $count = $generator->generateForSchedule($schedule);

        return redirect()->route('schedules.index')
            ->with('success', "スケジュールを登録し、{$count} 件のセッションを生成しました。");
    }

    public function destroy(ClassSchedule $schedule)
    {
        $schedule->delete();
        return redirect()->route('schedules.index')->with('success', 'スケジュールを削除しました。');
    }

    public function generate(ClassSchedule $schedule, SessionGenerator $generator)
    {
        $count = $generator->generateForSchedule($schedule);
        return redirect()->route('schedules.index')
            ->with('success', "{$count} 件のセッションを生成しました。");
    }
}
