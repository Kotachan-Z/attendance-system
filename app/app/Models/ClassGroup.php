<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClassGroup extends Model
{
    protected $fillable = ['name', 'sort_order'];

    /** 表示順（sort_order → 名前）に並べた組の一覧 */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    /** プルダウン用の組名リスト（表示順） */
    public static function options(): array
    {
        return static::ordered()->pluck('name')->all();
    }
}
