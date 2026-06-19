@extends('layouts.student')
@section('title', 'パスワード変更')

@section('content')
<div class="max-w-md mx-auto">
    <h1 class="text-2xl font-bold text-gray-800 mb-6">パスワード変更</h1>

    <form method="POST" action="{{ route('student.password.update') }}"
          class="bg-white rounded-xl shadow p-6 space-y-5">
        @csrf
        @method('PUT')

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">現在のパスワード</label>
            <input type="password" name="current_password" required autocomplete="current-password"
                   class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-400">
            @error('current_password')
                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">
                新しいパスワード
                <span class="text-gray-400 font-normal text-xs ml-1">6文字以上</span>
            </label>
            <input type="password" name="password" required autocomplete="new-password"
                   class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-400">
            @error('password')
                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">新しいパスワード（確認）</label>
            <input type="password" name="password_confirmation" required autocomplete="new-password"
                   class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-400">
        </div>

        <div class="flex gap-3 pt-2">
            <button type="submit"
                    class="bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-medium px-5 py-2 rounded-lg">
                変更する
            </button>
            <a href="{{ route('student.dashboard') }}"
               class="bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm font-medium px-5 py-2 rounded-lg">
                キャンセル
            </a>
        </div>
    </form>
</div>
@endsection
