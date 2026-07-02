<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\SavesBase64Image;
use App\Http\Controllers\Controller;
use App\Models\DetectionLog;
use Illuminate\Http\Request;

class DetectionController extends Controller
{
    use SavesBase64Image;

    /**
     * カメラが弾いた検出イベント（なりすまし疑い・識別不能）を記録する。
     * 出席記録と違い「失敗の証跡」なので、画像が無くても受け付ける。
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'reason'                => 'required|in:spoofing,unknown',
            'attendance_session_id' => 'nullable|exists:attendance_sessions,id',
            'matched_student_id'    => 'nullable|exists:students,id',
            // コサイン距離（0=同一 〜 2=逆方向）。未登録者は 1 を超えることがある
            'similarity_score'      => 'nullable|numeric|min:0|max:2',
            'depth_std_dev'         => 'nullable|numeric|min:0',
            'captured_image'        => 'nullable|string', // base64
        ]);

        $imagePath = null;
        if (! empty($validated['captured_image'])) {
            $dir       = 'detections/' . now()->format('Y-m-d');
            $imagePath = $this->saveBase64Image($validated['captured_image'], $dir);
            // 画像が不正でもログ自体は残す（証跡を失わないため画像だけ捨てる）
        }

        $log = DetectionLog::create([
            'attendance_session_id' => $validated['attendance_session_id'] ?? null,
            'matched_student_id'    => $validated['matched_student_id'] ?? null,
            'reason'                => $validated['reason'],
            'similarity_score'      => $validated['similarity_score'] ?? null,
            'depth_std_dev'         => $validated['depth_std_dev'] ?? null,
            'captured_image_path'   => $imagePath,
            'detected_at'           => now(),
        ]);

        return response()->json([
            'message' => '検出ログを記録しました',
            'id'      => $log->id,
        ], 201);
    }
}
