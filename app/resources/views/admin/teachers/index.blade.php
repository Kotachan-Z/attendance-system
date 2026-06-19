@extends('layouts.app')
@section('title', '教職員アカウント管理')

@section('content')
<div class="mb-6 flex items-center justify-between gap-3">
    <h1 class="text-2xl font-bold text-gray-800">教職員アカウント管理</h1>
</div>

@if ($errors->any())
    <div class="mb-4 bg-red-100 border border-red-400 text-red-800 rounded px-4 py-3 text-sm">
        <ul class="list-disc list-inside space-y-0.5">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">
    {{-- アカウント発行フォーム --}}
    <div class="lg:col-span-1 min-w-0">
        <div class="bg-white rounded-xl shadow p-5">
            <h2 class="text-base font-semibold text-gray-700 mb-4">アカウントを発行</h2>
            <form method="POST" action="{{ route('admin.teachers.store') }}" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-sm text-gray-600 mb-1">氏名</label>
                    <input type="text" name="name" value="{{ old('name') }}" required
                           class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400">
                </div>
                <div>
                    <label class="block text-sm text-gray-600 mb-1">メールアドレス</label>
                    <input type="email" name="email" value="{{ old('email') }}" required
                           class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400">
                </div>
                <div>
                    <label class="block text-sm text-gray-600 mb-1">初期パスワード</label>
                    <input type="text" name="password" required minlength="8"
                           class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400">
                    <p class="text-xs text-gray-400 mt-1">8文字以上。本人に伝えてください。</p>
                </div>
                <div>
                    <label class="block text-sm text-gray-600 mb-1">権限</label>
                    <select name="role" required
                            class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400">
                        <option value="teacher" @selected(old('role') === 'teacher')>教員</option>
                        <option value="admin" @selected(old('role') === 'admin')>管理者</option>
                    </select>
                    <p class="text-xs text-gray-400 mt-1">管理者は教員の全権限に加え、アカウント・授業・スケジュール管理が可能です。</p>
                </div>
                <button type="submit"
                        class="w-full bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-4 py-2 rounded-lg">
                    発行する
                </button>
            </form>
        </div>
    </div>

    {{-- アカウント一覧（カード型リスト：列が重ならない） --}}
    <div class="lg:col-span-2 min-w-0">
        <div class="bg-white rounded-xl shadow divide-y divide-gray-100">
            @foreach ($users as $user)
            <div class="p-4">
                <div class="flex flex-wrap items-center gap-x-3 gap-y-2">
                    {{-- 氏名・メール・権限 --}}
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="font-medium text-gray-800 break-all">{{ $user->name }}</span>
                            @if ($user->role === 'admin')
                                <span class="shrink-0 inline-block text-xs bg-amber-100 text-amber-700 rounded-full px-2 py-0.5">管理者</span>
                            @else
                                <span class="shrink-0 inline-block text-xs bg-sky-100 text-sky-700 rounded-full px-2 py-0.5">教員</span>
                            @endif
                        </div>
                        <p class="text-xs text-gray-500 break-all mt-0.5">{{ $user->email }}</p>
                    </div>

                    {{-- 操作 --}}
                    <div class="flex items-center gap-2 shrink-0">
                        <button type="button"
                                onclick="togglePwForm('pw-{{ $user->id }}')"
                                class="whitespace-nowrap text-xs font-medium text-indigo-600 border border-indigo-200 hover:bg-indigo-50 rounded-md px-2.5 py-1">パスワード再発行</button>
                        <form method="POST" action="{{ route('admin.teachers.destroy', $user) }}"
                              onsubmit="return confirm('「{{ $user->name }}」を削除しますか？')">
                            @csrf @method('DELETE')
                            <button type="submit"
                                    class="whitespace-nowrap text-xs font-medium text-red-500 border border-red-200 hover:bg-red-50 rounded-md px-2.5 py-1">削除</button>
                        </form>
                    </div>
                </div>

                {{-- パスワード再発行フォーム（トグル表示） --}}
                <div id="pw-{{ $user->id }}" class="hidden mt-3 pt-3 border-t border-gray-100">
                    <form method="POST" action="{{ route('admin.teachers.password', $user) }}"
                          class="flex flex-wrap items-center gap-2">
                        @csrf @method('PUT')
                        <span class="text-xs text-gray-500 whitespace-nowrap">新しいパスワード</span>
                        <input type="text" name="password" required minlength="8" placeholder="8文字以上"
                               class="flex-1 min-w-[160px] border rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400">
                        <button type="submit"
                                class="bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-medium px-4 py-1.5 rounded-lg whitespace-nowrap">更新</button>
                        <button type="button" onclick="togglePwForm('pw-{{ $user->id }}')"
                                class="text-xs text-gray-500 hover:underline px-2">閉じる</button>
                    </form>
                </div>
            </div>
            @endforeach
        </div>
    </div>
</div>

<script>
function togglePwForm(id) {
    document.getElementById(id).classList.toggle('hidden');
}
</script>
@endsection
