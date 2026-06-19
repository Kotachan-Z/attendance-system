<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            // 生徒ログイン用（学籍番号 + パスワード）。未発行の間は null。
            $table->string('password')->nullable()->after('class_name');
            $table->rememberToken()->after('password');
            // 退学フラグ。退学日時が入っていれば退学扱い（ログイン不可・名簿除外）。
            $table->timestamp('withdrawn_at')->nullable()->after('remember_token')->index();
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropIndex(['withdrawn_at']);
            $table->dropColumn(['password', 'remember_token', 'withdrawn_at']);
        });
    }
};
