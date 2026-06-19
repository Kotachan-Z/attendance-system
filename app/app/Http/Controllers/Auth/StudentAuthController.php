<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class StudentAuthController extends Controller
{
    /** ログイン試行の上限（回） */
    private const MAX_ATTEMPTS = 5;

    /** 生徒ログイン画面 */
    public function create()
    {
        return view('auth.student-login');
    }

    /** 生徒ログイン処理（学籍番号 + パスワード） */
    public function store(Request $request)
    {
        $credentials = $request->validate([
            'student_number' => ['required', 'string'],
            'password'       => ['required', 'string'],
        ]);

        // ブルートフォース対策: 短時間に何度も失敗したら一時的にロック
        $this->ensureIsNotRateLimited($request);

        // 学籍番号 + パスワードで認証
        if (! Auth::guard('student')->attempt($credentials, $request->boolean('remember'))) {
            RateLimiter::hit($this->throttleKey($request));
            throw ValidationException::withMessages([
                'student_number' => '学籍番号またはパスワードが正しくありません。',
            ]);
        }

        // 認証成功 → 失敗カウンタをリセット
        RateLimiter::clear($this->throttleKey($request));

        $student = Auth::guard('student')->user();

        // 退学者はログイン不可
        if ($student->isWithdrawn()) {
            $this->forceLogout($request);
            throw ValidationException::withMessages([
                'student_number' => 'このアカウントは現在利用できません。管理者に問い合わせてください。',
            ]);
        }

        // クラス（組）未割り当ての生徒ははじく
        if (! $student->hasClass()) {
            $this->forceLogout($request);
            throw ValidationException::withMessages([
                'student_number' => 'クラスが未割り当てです。担任・管理者に問い合わせてください。',
            ]);
        }

        $request->session()->regenerate();

        // intended が生徒エリア外（例: 職員用の "/"）を指していると、ログイン後に
        // 職員ガードへ弾かれて「職員ログイン画面に戻る」ループになる。
        // 生徒エリア(/student...)の intended だけ尊重し、それ以外は破棄する。
        $intended = $request->session()->get('url.intended');
        if ($intended) {
            $path = ltrim((string) parse_url($intended, PHP_URL_PATH), '/');
            if (! Str::startsWith($path, 'student')) {
                $request->session()->forget('url.intended');
            }
        }

        return redirect()->intended(route('student.dashboard'));
    }

    /** ログアウト */
    public function destroy(Request $request)
    {
        $this->forceLogout($request);
        return redirect()->route('student.login');
    }

    private function forceLogout(Request $request): void
    {
        Auth::guard('student')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
    }

    /** 試行回数が上限を超えていれば例外で弾く */
    private function ensureIsNotRateLimited(Request $request): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey($request), self::MAX_ATTEMPTS)) {
            return;
        }

        $seconds = RateLimiter::availableIn($this->throttleKey($request));

        throw ValidationException::withMessages([
            'student_number' => "ログイン試行が多すぎます。約{$seconds}秒後に再度お試しください。",
        ]);
    }

    /** 学籍番号 + IP 単位のレートリミットキー */
    private function throttleKey(Request $request): string
    {
        return Str::transliterate(
            Str::lower((string) $request->input('student_number')) . '|' . $request->ip()
        );
    }
}
