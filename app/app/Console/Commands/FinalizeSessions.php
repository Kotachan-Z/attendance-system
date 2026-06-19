<?php

namespace App\Console\Commands;

use App\Models\AttendanceSession;
use App\Services\AttendanceFinalizer;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class FinalizeSessions extends Command
{
    protected $signature = 'sessions:finalize';

    protected $description = '予定終了時刻を過ぎた未終了セッションを締めて欠席を記録する';

    public function handle(AttendanceFinalizer $finalizer): int
    {
        $now = Carbon::now();

        $sessions = AttendanceSession::whereNull('ended_at')
            ->whereNotNull('scheduled_end_at')
            ->where('scheduled_end_at', '<', $now)
            ->get();

        $closed = 0;
        $absent = 0;
        foreach ($sessions as $session) {
            $session->update(['ended_at' => $session->scheduled_end_at]);
            $absent += $finalizer->markAbsentees($session);
            $closed++;
        }

        $this->info("{$closed} 件のセッションを終了し、{$absent}名 を欠席として記録しました。");
        return self::SUCCESS;
    }
}
