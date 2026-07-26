<?php

namespace App\Http\Controllers;

use App\Models\ClassGroup;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ClassGroupController extends Controller
{
    /**
     * 新しい組を追加する（管理者のみ）。
     * 学生登録フォームなどから fetch で呼ばれ、JSON を返す。
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => [
                'required', 'string', 'max:50',
                // 英数字のみ（IE3A / SK1A のような校内コードを想定）
                'regex:/^[A-Za-z0-9]+$/',
                Rule::unique('class_groups', 'name'),
            ],
        ], [
            'name.regex'  => '組名は英数字で入力してください（例: IE3A）。',
            'name.unique' => 'その組はすでに登録されています。',
        ]);

        // 末尾に追加（既存の最大 sort_order + 1）
        $name  = strtoupper($validated['name']);
        $exists = ClassGroup::whereRaw('UPPER(name) = ?', [$name])->first();
        if ($exists) {
            return response()->json(['message' => 'その組はすでに登録されています。'], 422);
        }

        $group = ClassGroup::create([
            'name'       => $name,
            'sort_order' => (int) ClassGroup::max('sort_order') + 1,
        ]);

        return response()->json([
            'id'   => $group->id,
            'name' => $group->name,
        ], 201);
    }

    /**
     * 組を選択肢から削除する（管理者のみ）。
     * ※ 学生の class_name 文字列は変更しない（既存の割り当ては残す）。
     */
    public function destroy(ClassGroup $classGroup)
    {
        $classGroup->delete();
        return back()->with('success', "組「{$classGroup->name}」を選択肢から削除しました。");
    }
}
