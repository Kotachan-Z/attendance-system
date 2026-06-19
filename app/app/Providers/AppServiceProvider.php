<?php

namespace App\Providers;

use Illuminate\Auth\Middleware\RedirectIfAuthenticated;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // PHP の upload_max_filesize を超えたときに分かりやすいエラーメッセージを返す
        Validator::extend('uploaded_file_valid', function ($attr, $value) {
            return $value->isValid();
        }, 'アップロードに失敗しました。ファイルサイズが大きすぎる可能性があります。');

        // ログイン済みユーザーが guest 用ページ（ログイン画面など）に来たときの遷移先。
        //   既定では常に職員ダッシュボード(/)へ飛ぶため、生徒がログイン済みで
        //   /student/login を開くと職員ログインに弾かれてしまう。ガード別に振り分ける。
        RedirectIfAuthenticated::redirectUsing(function () {
            if (Auth::guard('student')->check()) {
                return route('student.dashboard');
            }
            return route('dashboard');
        });
    }
}
