<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class PasswordController extends Controller
{
    /** パスワード変更フォーム */
    public function edit()
    {
        return view('student.password');
    }

    /** パスワード変更処理 */
    public function update(Request $request)
    {
        $student = Auth::guard('student')->user();

        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password'         => ['required', 'string', 'min:6', 'max:255', 'confirmed'],
        ]);

        // 現在のパスワードを照合
        if (! Hash::check($validated['current_password'], (string) $student->password)) {
            throw ValidationException::withMessages([
                'current_password' => '現在のパスワードが正しくありません。',
            ]);
        }

        $student->password = $validated['password']; // hashed キャストで自動ハッシュ
        $student->save();

        return redirect()->route('student.dashboard')
            ->with('success', 'パスワードを変更しました。');
    }
}
