<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class AttendanceSession extends Model
{
    protected $fillable = [
        'course_id',
        'class_schedule_id',
        'session_date',
        'scheduled_start_at',
        'scheduled_end_at',
        'late_threshold_minutes',
        'started_at',
        'ended_at',
    ];

    protected $casts = [
        'session_date'       => 'date',
        'scheduled_start_at' => 'datetime',
        'scheduled_end_at'   => 'datetime',
        'started_at'         => 'datetime',
        'ended_at'           => 'datetime',
    ];

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function classSchedule()
    {
        return $this->belongsTo(ClassSchedule::class);
    }

    public function attendanceRecords()
    {
        return $this->hasMany(AttendanceRecord::class);
    }

    public function students()
    {
        return $this->belongsToMany(Student::class, 'attendance_records')
                    ->withPivot(['status', 'captured_image_path', 'similarity_score', 'verified_at'])
                    ->withTimestamps();
    }

    public function isActive(): bool
    {
        return is_null($this->ended_at);
    }

    /** 判定基準となる開始時刻（予定があれば予定、なければ実開始） */
    public function basisStart(): Carbon
    {
        return $this->scheduled_start_at ?? $this->started_at;
    }

    /**
     * 検出時刻から出席ステータスを判定する。
     *   開始まで              → present（出席）
     *   開始〜開始+閾値分以内   → late（遅刻）
     *   それ以降              → absent（欠席。出席しても欠席扱い）
     */
    public function determineStatus(Carbon $verifiedAt): string
    {
        $start     = $this->basisStart();
        $threshold = $this->late_threshold_minutes ?? 20;
        $lateLimit = $start->copy()->addMinutes($threshold);

        if ($verifiedAt->lessThanOrEqualTo($start)) {
            return 'present';
        }
        if ($verifiedAt->lessThanOrEqualTo($lateLimit)) {
            return 'late';
        }
        return 'absent';
    }
}
