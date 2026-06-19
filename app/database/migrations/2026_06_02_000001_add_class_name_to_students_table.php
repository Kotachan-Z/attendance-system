<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            // 組（ホームルームクラス）。例: "1年A組"。未設定可。
            $table->string('class_name', 50)->nullable()->after('student_number')->index();
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropIndex(['class_name']);
            $table->dropColumn('class_name');
        });
    }
};
