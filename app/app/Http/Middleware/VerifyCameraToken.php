<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class VerifyCameraToken
{
    /** 配布時の初期トークン（本番でこのままは危険） */
    private const PLACEHOLDER_TOKEN = 'attendance-camera-secret-token-change-in-production';

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $expected = (string) config('app.camera_api_token');

        // トークン未設定 or 初期値のまま → 設定漏れを警告。本番では受け付けない。
        if ($expected === '' || hash_equals(self::PLACEHOLDER_TOKEN, $expected)) {
            Log::warning('CAMERA_API_TOKEN が未設定または初期値のままです。.env で推測困難な値に変更してください。');

            if (app()->environment('production')) {
                return response()->json(
                    ['error' => 'カメラAPIトークンが未設定です。管理者に連絡してください。'],
                    503
                );
            }
        }

        $token = $request->bearerToken();

        // タイミング攻撃対策に hash_equals で定数時間比較する
        if ($expected === '' || ! $token || ! hash_equals($expected, $token)) {
            return response()->json(['error' => '認証に失敗しました'], 401);
        }

        return $next($request);
    }
}
