<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        // 初期教員アカウント（本番環境では必ずパスワードを変更すること）
        User::firstOrCreate(
            ['email' => 'teacher@example.com'],
            [
                'name'     => '教員',
                'password' => bcrypt('password'),
            ]
        );
    }
}
