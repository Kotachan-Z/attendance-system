<?php

namespace App\Console\Commands;

use App\Services\SessionGenerator;
use Illuminate\Console\Command;

class GenerateSessions extends Command
{
    protected $signature = 'sessions:generate';

    protected $description = '登録済みスケジュールから出席セッションを自動生成する';

    public function handle(SessionGenerator $generator): int
    {
        $count = $generator->generateAll();
        $this->info("{$count} 件のセッションを生成しました。");
        return self::SUCCESS;
    }
}
