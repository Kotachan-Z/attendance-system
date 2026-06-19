<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            // 開始から何分以内なら「遅刻」とするか（これを超えると出席しても欠席扱い）
            $table->unsignedSmallInteger('late_threshold_minutes')->default(20)->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->dropColumn('late_threshold_minutes');
        });
    }
};
