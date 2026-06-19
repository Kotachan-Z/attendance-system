<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_sessions', function (Blueprint $table) {
            // スケジュールから自動生成された場合の元スケジュール
            $table->foreignId('class_schedule_id')->nullable()->after('course_id')
                  ->constrained()->nullOnDelete();

            // 予定上の開始・終了時刻（出席/遅刻/欠席の判定基準）
            $table->timestamp('scheduled_start_at')->nullable()->after('session_date');
            $table->timestamp('scheduled_end_at')->nullable()->after('scheduled_start_at');

            // セッション作成時の遅刻閾値（コースからコピー）
            $table->unsignedSmallInteger('late_threshold_minutes')->default(20)->after('scheduled_end_at');

            // 同一スケジュール・同一日のセッションを重複生成しない
            $table->unique(['class_schedule_id', 'session_date'], 'sessions_schedule_date_unique');
        });
    }

    public function down(): void
    {
        Schema::table('attendance_sessions', function (Blueprint $table) {
            $table->dropUnique('sessions_schedule_date_unique');
            $table->dropConstrainedForeignId('class_schedule_id');
            $table->dropColumn(['scheduled_start_at', 'scheduled_end_at', 'late_threshold_minutes']);
        });
    }
};
