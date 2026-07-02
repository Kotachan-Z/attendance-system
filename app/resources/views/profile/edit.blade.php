@extends('layouts.app')
@section('title', 'アカウント設定')

@section('content')
<div class="max-w-xl mx-auto">
    <h1 class="text-2xl font-bold text-gray-800 mb-1">アカウント設定</h1>
    <p class="text-sm text-gray-500 mb-6">自分の表示名・メールアドレス・パスワードを変更できます。</p>

    {{-- 更新完了メッセージ --}}
    @if (session('status') === 'profile-updated')
        <div class="mb-4 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-lg px-4 py-3 text-sm">
            プロフィールを更新しました。
        </div>
    @elseif (session('status') === 'password-updated')
        <div class="mb-4 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-lg px-4 py-3 text-sm">
            パスワードを変更しました。
        </div>
    @endif

    {{-- プロフィール情報 --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 mb-6">
        <h2 class="text-base font-semibold text-gray-700 mb-4">プロフィール情報</h2>

        <form method="POST" action="{{ route('profile.update') }}" class="space-y-4">
            @csrf
            @method('PATCH')

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">表示名</label>
                <input type="text" name="name" value="{{ old('name', Auth::user()->name) }}" required
                       class="w-full border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-300 border">
                @error('name')
                    <p class="text-rose-500 text-xs mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">メールアドレス</label>
                <input type="email" name="email" value="{{ old('email', Auth::user()->email) }}" required
                       class="w-full border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-300 border">
                @error('email')
                    <p class="text-rose-500 text-xs mt-1">{{ $message }}</p>
                @enderror
            </div>

            <button type="submit"
                    class="bg-indigo-500 hover:bg-indigo-600 text-white text-sm font-medium px-5 py-2 rounded-lg">
                保存
            </button>
        </form>
    </div>

    {{-- パスワード変更 --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
        <h2 class="text-base font-semibold text-gray-700 mb-1">パスワード変更</h2>
        <p class="text-xs text-gray-400 mb-4">他人に推測されにくい長いパスワードを設定してください。</p>

        <form method="POST" action="{{ route('password.update') }}" class="space-y-4">
            @csrf
            @method('PUT')

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">現在のパスワード</label>
                <input type="password" name="current_password" autocomplete="current-password"
                       class="w-full border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-300 border">
                @error('current_password', 'updatePassword')
                    <p class="text-rose-500 text-xs mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">新しいパスワード</label>
                <input type="password" name="password" autocomplete="new-password"
                       class="w-full border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-300 border">
                @error('password', 'updatePassword')
                    <p class="text-rose-500 text-xs mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">新しいパスワード（確認）</label>
                <input type="password" name="password_confirmation" autocomplete="new-password"
                       class="w-full border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-300 border">
                @error('password_confirmation', 'updatePassword')
                    <p class="text-rose-500 text-xs mt-1">{{ $message }}</p>
                @enderror
            </div>

            <button type="submit"
                    class="bg-indigo-500 hover:bg-indigo-600 text-white text-sm font-medium px-5 py-2 rounded-lg">
                パスワードを変更
            </button>
        </form>
    </div>
</div>
@endsection
