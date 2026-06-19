<?php

namespace App\Console\Commands;

use App\Models\Student;
use Illuminate\Console\Command;

class ResetStudentPasswords extends Command
{
    protected $signature = 'students:reset-passwords
                            {--only-empty : パスワード未設定の学生だけに発行する}';

    protected $description = '学生のログインパスワードを学籍番号と同じ値に（再）設定する';

    public function handle(): int
    {
        $query = Student::query();

        if ($this->option('only-empty')) {
            $query->whereNull('password');
        }

        $total = (clone $query)->count();
        if ($total === 0) {
            $this->info('対象の学生がいません。');
            return self::SUCCESS;
        }

        if (! $this->option('only-empty')
            && ! $this->confirm("{$total} 名のパスワードを「学籍番号と同じ値」に上書きします。よろしいですか？", true)) {
            $this->warn('中止しました。');
            return self::SUCCESS;
        }

        $count = 0;
        $query->chunkById(200, function ($students) use (&$count) {
            foreach ($students as $student) {
                $student->password = $student->student_number; // hashed キャストで自動ハッシュ
                $student->save();
                $count++;
            }
        });

        $this->info("{$count} 名のパスワードを学籍番号と同じ値に設定しました。");

        return self::SUCCESS;
    }
}
