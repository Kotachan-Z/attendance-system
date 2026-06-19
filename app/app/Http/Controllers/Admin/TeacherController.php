<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TeacherController extends Controller
{
    /** 教職員アカウント一覧 + 追加フォーム */
    public function index()
    {
        $users = User::orderByRaw("FIELD(role,'admin','teacher')")
            ->orderBy('name')
            ->get();

        return view('admin.teachers.index', compact('users'));
    }

    /** 教職員アカウントを発行する */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|max:255|unique:users,email',
            'password' => 'required|string|min:8|max:255',
            'role'     => ['required', Rule::in(['admin', 'teacher'])],
        ]);

        User::create([
            'name'              => $validated['name'],
            'email'             => $validated['email'],
            'password'          => $validated['password'], // hashed キャストで自動ハッシュ
            'role'              => $validated['role'],
            'email_verified_at' => now(), // 管理者発行のため確認済み扱い
        ]);

        $label = $validated['role'] === 'admin' ? '管理者' : '教員';

        return redirect()->route('admin.teachers.index')
            ->with('success', "{$label}アカウント「{$validated['name']}」を発行しました。");
    }

    /** パスワード再発行 */
    public function resetPassword(Request $request, User $user)
    {
        $validated = $request->validate([
            'password' => 'required|string|min:8|max:255',
        ]);

        $user->update(['password' => $validated['password']]);

        return redirect()->route('admin.teachers.index')
            ->with('success', "「{$user->name}」のパスワードを再発行しました。");
    }

    /** アカウント削除（自分自身と最後の管理者は削除不可） */
    public function destroy(Request $request, User $user)
    {
        if ($user->id === $request->user()->id) {
            return redirect()->route('admin.teachers.index')
                ->with('error', '自分自身のアカウントは削除できません。');
        }

        if ($user->isAdmin() && User::where('role', 'admin')->count() <= 1) {
            return redirect()->route('admin.teachers.index')
                ->with('error', '管理者が0人になるため削除できません。');
        }

        $user->delete();

        return redirect()->route('admin.teachers.index')
            ->with('success', "「{$user->name}」を削除しました。");
    }
}
