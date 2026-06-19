<?php

namespace App\Http\Controllers;

use App\Models\DetectionLog;
use Illuminate\Http\Request;

class DetectionLogController extends Controller
{
    /** なりすまし疑い・識別不能の検出ログ一覧（管理者向け） */
    public function index(Request $request)
    {
        $reason = $request->query('reason');

        $logs = DetectionLog::with(['matchedStudent', 'attendanceSession.course'])
            ->when(in_array($reason, ['spoofing', 'unknown'], true),
                fn ($q) => $q->where('reason', $reason))
            ->latest('detected_at')
            ->paginate(30)
            ->withQueryString();

        $counts = [
            'spoofing' => DetectionLog::where('reason', 'spoofing')->count(),
            'unknown'  => DetectionLog::where('reason', 'unknown')->count(),
        ];

        return view('detections.index', [
            'logs'   => $logs,
            'counts' => $counts,
            'reason' => $reason,
        ]);
    }
}
