<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 組（クラス）の選択肢マスタ。
     * 学生の class_name はこの一覧から選ばせる（プルダウン）。
     * class_name 自体は students テーブルに文字列で保持したまま（既存の絞り込み・
     * 一括登録・API を壊さないため）、ここは選択肢の供給元として使う。
     */
    public function up(): void
    {
        Schema::create('class_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50)->unique();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        // 初期の組（指定された並び順を sort_order で保持）
        $defaults = ['IE3A', 'IE3B', 'IE2A', 'IE2B', 'IE1A', 'IE1B', 'SK1A', 'SK2A', 'SK3A'];
        $now = now();
        $rows = [];
        foreach ($defaults as $i => $name) {
            $rows[] = ['name' => $name, 'sort_order' => $i, 'created_at' => $now, 'updated_at' => $now];
        }
        DB::table('class_groups')->insert($rows);

        // 既存の学生が使っている組で、初期一覧に無いものは失わないよう取り込む
        if (Schema::hasTable('students')) {
            $existing = DB::table('students')
                ->whereNotNull('class_name')
                ->where('class_name', '!=', '')
                ->distinct()
                ->pluck('class_name')
                ->diff($defaults);

            $order = count($defaults);
            foreach ($existing as $name) {
                DB::table('class_groups')->insertOrIgnore([
                    'name' => $name, 'sort_order' => $order++,
                    'created_at' => $now, 'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('class_groups');
    }
};
