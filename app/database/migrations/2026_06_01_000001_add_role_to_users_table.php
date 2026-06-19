<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // admin: スケジュール・授業の管理が可能 / teacher: 出席確認のみ
            $table->string('role', 20)->default('teacher')->after('password');
        });

        // 既存の最初のユーザー（開発者）を admin に昇格
        DB::table('users')->orderBy('id')->limit(1)->update(['role' => 'admin']);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('role');
        });
    }
};
