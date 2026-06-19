<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('class_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();

            // 'weekly' = 曜日繰り返し / 'onetime' = 特定日の単発
            $table->string('type', 10)->default('weekly');

            // weekly 用: 0=日, 1=月, ... 6=土
            $table->unsignedTinyInteger('day_of_week')->nullable();
            // weekly 用: 有効期間（◯月〜◯月）
            $table->date('effective_from')->nullable();
            $table->date('effective_until')->nullable();

            // onetime 用: 特定日
            $table->date('specific_date')->nullable();

            // 共通: 開始・終了時刻
            $table->time('start_time');
            $table->time('end_time');

            $table->timestamps();

            $table->index(['type', 'day_of_week']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('class_schedules');
    }
};
