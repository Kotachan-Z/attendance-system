<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// スケジュールから出席セッションを毎日自動生成
Schedule::command('sessions:generate')->dailyAt('00:05');

// 予定終了時刻を過ぎたセッションを締めて欠席を記録（5分ごと）
Schedule::command('sessions:finalize')->everyFiveMinutes();
