<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AttendanceSession;
use Illuminate\Support\Carbon;

class AttendanceSessionController extends Controller
{
    public function active()
    {
        $now = Carbon::now();

        // 1) 予定時間内（scheduled_start 〜 scheduled_end）の未終了セッションを優先
        $session = AttendanceSession::whereNull('ended_at')
            ->whereNotNull('scheduled_start_at')
            ->where('scheduled_start_at', '<=', $now)
            ->where('scheduled_end_at', '>=', $now)
            ->with('course')
            ->orderBy('scheduled_start_at')
            ->first();

        // 2) 予定枠が無ければ、手動開始の未終了セッション（従来動作）
        if (! $session) {
            $session = AttendanceSession::whereNull('ended_at')
                ->whereNull('scheduled_start_at')
                ->with('course')
                ->latest('started_at')
                ->first();
        }

        if (! $session) {
            return response()->json(['message' => '進行中のセッションはありません'], 404);
        }

        return response()->json([
            'id'                     => $session->id,
            'course_name'            => $session->course->name,
            'session_date'           => $session->session_date->toDateString(),
            'started_at'             => $session->started_at->toIso8601String(),
            'scheduled_start_at'     => optional($session->scheduled_start_at)->toIso8601String(),
            'scheduled_end_at'       => optional($session->scheduled_end_at)->toIso8601String(),
            'late_threshold_minutes' => $session->late_threshold_minutes,
        ]);
    }
}
