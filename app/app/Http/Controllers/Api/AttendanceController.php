<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\SavesBase64Image;
use App\Http\Controllers\Controller;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\Student;
use Illuminate\Http\Request;

class AttendanceController extends Controller
{
    use SavesBase64Image;

    public function store(Request $request)
    {
        $validated = $request->validate([
            'student_id'           => 'required|exists:students,id',
            'attendance_session_id' => 'required|exists:attendance_sessions,id',
            'similarity_score'     => 'required|numeric|min:0|max:1',
            'captured_image'       => 'required|string', // base64
        ]);

        $session = AttendanceSession::findOrFail($validated['attendance_session_id']);
        if (! $session->isActive()) {
            return response()->json(['error' => 'セッションはすでに終了しています'], 422);
        }

        $student = Student::findOrFail($validated['student_id']);

        // 退学者は出席記録の対象外
        if ($student->isWithdrawn()) {
            return response()->json(['error' => 'この学生は現在出席記録の対象外です'], 422);
        }

        // そのセッションのコースを履修している学生のみ記録する
        $enrolled = $session->course
            ->students()
            ->whereKey($student->getKey())
            ->exists();
        if (! $enrolled) {
            return response()->json(['error' => 'この学生はこの授業を履修していません'], 422);
        }

        $exists = AttendanceRecord::where('student_id', $validated['student_id'])
                                  ->where('attendance_session_id', $validated['attendance_session_id'])
                                  ->exists();
        if ($exists) {
            return response()->json(['message' => 'すでに出席済みです', 'duplicate' => true], 200);
        }

        // base64 画像を保存（不正な画像は弾く）
        $captureDir = 'captures/' . now()->format('Y-m-d') . '/session_' . $validated['attendance_session_id'];
        $imagePath  = $this->saveBase64Image($validated['captured_image'], $captureDir);
        if ($imagePath === null) {
            return response()->json(['error' => '画像データが不正です（JPEG/PNG のみ・最大8MB）'], 422);
        }

        // 検出時刻から 出席/遅刻/欠席 を判定
        $verifiedAt = now();
        $status     = $session->determineStatus($verifiedAt);

        $record = AttendanceRecord::create([
            'student_id'            => $validated['student_id'],
            'attendance_session_id' => $validated['attendance_session_id'],
            'status'                => $status,
            'similarity_score'      => $validated['similarity_score'],
            'captured_image_path'   => $imagePath,
            'verified_at'           => $verifiedAt,
        ]);

        $record->load('student');

        return response()->json([
            'message' => '出席を記録しました',
            'record'  => [
                'id'             => $record->id,
                'student_name'   => $record->student->name,
                'student_number' => $record->student->student_number,
                'status'         => $record->status,
                'status_label'   => $record->statusLabel(),
                'verified_at'    => $record->verified_at->toIso8601String(),
            ],
        ], 201);
    }
}
