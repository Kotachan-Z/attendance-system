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

    /**
     * 進行中か。
     *   予定あり（時間割由来）: 予定開始〜予定終了の「今その時間」だけ進行中。
     *     → 未来のコマは「予定」、時間を過ぎたものは「終了」扱いになる。
     *   予定なし（手動開始）: 終了されるまで進行中（従来動作）。
     */
    public function isActive(): bool
    {
        if (! is_null($this->ended_at)) {
            return false;
        }
        if ($this->scheduled_start_at) {
            $now = Carbon::now();
            return $this->scheduled_start_at->lessThanOrEqualTo($now)
                && (is_null($this->scheduled_end_at) || $this->scheduled_end_at->greaterThanOrEqualTo($now));
        }
        return true;
    }

    /** 予定開始がまだ来ていない（＝これから始まる予定）か。 */
    public function isUpcoming(): bool
    {
        return is_null($this->ended_at)
            && $this->scheduled_start_at
            && $this->scheduled_start_at->isFuture();
    }

    /** 表示用の状態: 'upcoming'（予定）/ 'active'（進行中）/ 'ended'（終了）。 */
    public function liveStatus(): string
    {
        if ($this->isUpcoming()) {
            return 'upcoming';
        }
        return $this->isActive() ? 'active' : 'ended';
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
