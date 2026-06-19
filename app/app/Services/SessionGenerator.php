<?php

namespace App\Services;

use App\Models\AttendanceSession;
use App\Models\ClassSchedule;

class SessionGenerator
{
    /**
     * 1つのスケジュールから attendance_sessions を生成する。
     * 既存（class_schedule_id + session_date が一致）はスキップ。
     *
     * @return int 新規作成したセッション数
     */
    public function generateForSchedule(ClassSchedule $schedule): int
    {
        $schedule->loadMissing('course');
        $threshold = $schedule->course->late_threshold_minutes ?? 20;

        $created = 0;
        foreach ($schedule->occurrenceDates() as $date) {
            [$start, $end] = $schedule->scheduledRangeFor($date);

            $exists = AttendanceSession::where('class_schedule_id', $schedule->id)
                ->whereDate('session_date', $date->toDateString())
                ->exists();
            if ($exists) {
                continue;
            }

            AttendanceSession::create([
                'course_id'              => $schedule->course_id,
                'class_schedule_id'      => $schedule->id,
                'session_date'           => $date->toDateString(),
                'scheduled_start_at'     => $start,
                'scheduled_end_at'       => $end,
                'late_threshold_minutes' => $threshold,
                // 自動生成のため実開始時刻は予定開始に合わせておく（判定は scheduled を優先）
                'started_at'             => $start,
            ]);
            $created++;
        }

        return $created;
    }

    /**
     * 全スケジュールからセッションを生成する。
     *
     * @return int 新規作成したセッション総数
     */
    public function generateAll(): int
    {
        $total = 0;
        foreach (ClassSchedule::with('course')->get() as $schedule) {
            $total += $this->generateForSchedule($schedule);
        }
        return $total;
    }
}
