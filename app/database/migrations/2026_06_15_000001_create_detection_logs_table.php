<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('detection_logs', function (Blueprint $table) {
            $table->id();

            // どのセッション中の検出か（セッション外の検出もあり得るので nullable）
            $table->foreignId('attendance_session_id')->nullable()
                  ->constrained()->nullOnDelete();

            // なりすまし判定時に「誰として照合されたか」。識別不能(unknown)なら null。
            $table->foreignId('matched_student_id')->nullable()
                  ->constrained('students')->nullOnDelete();

            // spoofing = なりすまし（深度チェック不合格） / unknown = 識別不能
            $table->string('reason', 20);

            // 顔照合のコサイン距離（unknown 時はベスト距離）。0〜1 程度。
            $table->decimal('similarity_score', 6, 4)->nullable();

            // 深度の標準偏差(mm)。spoofing 判定の根拠。
            $table->float('depth_std_dev')->nullable();

            $table->string('captured_image_path')->nullable();
            $table->timestamp('detected_at');
            $table->timestamps();

            $table->index(['reason', 'detected_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('detection_logs');
    }
};
