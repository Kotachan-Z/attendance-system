<?php

namespace App\Http\Controllers;

use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AttendanceRecordController extends Controller
{
    /**
     * 出欠を手動で修正する（公欠などの理由で出席扱いにする等）。
     * 管理者・先生のどちらも操作可能。記録が無ければ作成する。
     */
    public function updateStatus(Request $request, AttendanceSession $session, Student $student)
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(['present', 'late', 'absent'])],
            'note'   => 'nullable|string|max:255',
        ]);

        $record = AttendanceRecord::firstOrNew([
            'attendance_session_id' => $session->id,
            'student_id'            => $student->id,
        ]);

        $record->status              = $validated['status'];
        $record->note                = filled($validated['note'] ?? null) ? $validated['note'] : null;
        $record->manually_updated_at = now();
        $record->save();

        $label = AttendanceRecord::STATUS_LABELS[$validated['status']] ?? $validated['status'];

        return back()->with('success', "{$student->name} の出欠を「{$label}」に修正しました。");
    }
}
