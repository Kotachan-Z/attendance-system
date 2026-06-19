<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('attendance_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('attendance_session_id')->constrained()->cascadeOnDelete();
            $table->string('captured_image_path')->nullable();
            $table->float('similarity_score')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
            $table->unique(['student_id', 'attendance_session_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendance_records');
    }
};
