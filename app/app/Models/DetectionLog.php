<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DetectionLog extends Model
{
    protected $fillable = [
        'attendance_session_id',
        'matched_student_id',
        'reason',
        'similarity_score',
        'depth_std_dev',
        'captured_image_path',
        'detected_at',
    ];

    protected $casts = [
        'detected_at'      => 'datetime',
        'similarity_score' => 'float',
        'depth_std_dev'    => 'float',
    ];

    public const REASON_LABELS = [
        'spoofing' => 'なりすまし疑い',
        'unknown'  => '識別不能（未登録）',
    ];

    public function reasonLabel(): string
    {
        return self::REASON_LABELS[$this->reason] ?? $this->reason;
    }

    public function attendanceSession()
    {
        return $this->belongsTo(AttendanceSession::class);
    }

    public function matchedStudent()
    {
        return $this->belongsTo(Student::class, 'matched_student_id');
    }
}
