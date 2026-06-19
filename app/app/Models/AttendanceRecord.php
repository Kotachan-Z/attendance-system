<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AttendanceRecord extends Model
{
    protected $fillable = [
        'student_id',
        'attendance_session_id',
        'status',
        'note',
        'manually_updated_at',
        'captured_image_path',
        'similarity_score',
        'verified_at',
    ];

    protected $casts = [
        'verified_at'         => 'datetime',
        'manually_updated_at' => 'datetime',
    ];

    /** 手動で修正された記録か */
    public function isManual(): bool
    {
        return ! is_null($this->manually_updated_at);
    }

    public const STATUS_LABELS = [
        'present' => '出席',
        'late'    => '遅刻',
        'absent'  => '欠席',
    ];

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function attendanceSession()
    {
        return $this->belongsTo(AttendanceSession::class);
    }
}
