<?php

namespace App\Services;

use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;

class AttendanceFinalizer
{
    /**
     * セッション終了時、履修学生のうち出席記録が無い者を「欠席」として記録する。
     *
     * @return int 新規に欠席登録した人数
     */
    public function markAbsentees(AttendanceSession $session): int
    {
        // 既に記録のある学生ID（出席・遅刻・欠席のいずれか）
        $recordedIds = $session->attendanceRecords()->pluck('student_id')->all();

        // 在籍中（退学していない）の履修学生のみ対象
        $students = $session->course->students()->active()->get();

        $created = 0;
        foreach ($students as $student) {
            if (in_array($student->id, $recordedIds, true)) {
                continue;
            }

            AttendanceRecord::create([
                'student_id'            => $student->id,
                'attendance_session_id' => $session->id,
                'status'                => 'absent',
                'similarity_score'      => null,
                'captured_image_path'   => null,
                'verified_at'           => null,
            ]);
            $created++;
        }

        return $created;
    }
}
