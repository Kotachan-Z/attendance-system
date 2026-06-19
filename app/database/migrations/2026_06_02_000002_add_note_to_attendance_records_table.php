<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_records', function (Blueprint $table) {
            // 手動修正時の理由メモ（例: 公欠）。任意。
            $table->string('note', 255)->nullable()->after('status');
            // 手動で修正された日時（自動判定との区別・履歴表示用）。
            $table->timestamp('manually_updated_at')->nullable()->after('note');
        });
    }

    public function down(): void
    {
        Schema::table('attendance_records', function (Blueprint $table) {
            $table->dropColumn(['note', 'manually_updated_at']);
        });
    }
};
