@extends('layouts.app')
@section('title', '学生一覧')

@section('content')
<div class="mb-6 flex items-center justify-between gap-3">
    <h1 class="text-2xl font-bold text-gray-800">学生一覧</h1>
    <div class="flex items-center gap-2">
        @if ($students->isNotEmpty())
        <button type="button" id="select-toggle" onclick="toggleSelectMode()"
                class="text-sm font-medium px-4 py-2 rounded border border-indigo-200 text-indigo-600 hover:bg-indigo-50">
            クラス一括登録
        </button>
        @endif
        <a href="{{ route('students.create') }}"
           class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-4 py-2 rounded">
            + 学生登録
        </a>
    </div>
</div>

@if ($students->isEmpty())
    <p class="text-gray-500">学生が登録されていません。</p>
@else
    {{-- 選択モードの操作ヒント --}}
    <div id="select-hint" class="hidden bg-indigo-50 border border-indigo-200 text-indigo-700 rounded-lg px-4 py-2.5 text-sm mb-4 flex items-center justify-between gap-3">
        <span>登録したい学生をタップして選択 → 下のバーで組を入力して登録します。</span>
        <button type="button" onclick="selectAll(true)" class="underline whitespace-nowrap">すべて選択</button>
    </div>

    {{-- 組（クラス）ごとにグループ表示 --}}
    @foreach ($grouped as $className => $list)
    <div class="mb-8">
        <div class="flex items-center gap-2 mb-3">
            <h2 class="text-base font-semibold text-gray-700">
                {{ $className !== '' ? $className : '未分類（組なし）' }}
            </h2>
            <span class="text-xs text-gray-400 bg-gray-100 rounded-full px-2 py-0.5">{{ $list->count() }}名</span>
            <button type="button" onclick="selectGroup(this)"
                    class="select-only hidden text-xs text-indigo-600 hover:underline ml-1">この組を選択</button>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            @foreach ($list as $student)
            <label class="student-card relative bg-white rounded-xl shadow p-4 flex gap-4 items-center cursor-default">
                {{-- 選択チェックボックス（選択モード時のみ表示）。HTML5 form 属性で下のフォームに紐付け --}}
                <input type="checkbox" name="student_ids[]" value="{{ $student->id }}" form="bulk-form"
                       class="bulk-cb hidden absolute top-3 left-3 w-5 h-5 accent-indigo-600"
                       onchange="updateBar()">

                @if ($student->face_image_path)
                    <img src="{{ Storage::url($student->face_image_path) }}"
                         alt="{{ $student->name }}"
                         class="w-16 h-16 rounded-full object-cover border-2 border-indigo-200">
                @else
                    <div class="w-16 h-16 rounded-full bg-gray-200 flex items-center justify-center text-gray-400 text-2xl">?</div>
                @endif
                <div class="flex-1 min-w-0">
                    <p class="font-semibold text-gray-800 truncate">{{ $student->name }}</p>
                    <p class="text-sm text-gray-500">{{ $student->student_number }}</p>
                    <p class="text-xs text-gray-400">出席回数: {{ $student->attendance_records_count }}回</p>
                </div>
                <div class="card-actions flex flex-col gap-1">
                    <a href="{{ route('students.show', $student) }}"
                       class="text-xs text-indigo-600 hover:underline">詳細</a>
                    <form method="POST" action="{{ route('students.destroy', $student) }}"
                          onsubmit="return confirm('削除しますか？')">
                        @csrf @method('DELETE')
                        <button type="submit" class="text-xs text-red-500 hover:underline">削除</button>
                    </form>
                </div>
            </label>
            @endforeach
        </div>
    </div>
    @endforeach

    {{-- 退学者一覧（管理者のみ） --}}
    @if (Auth::user()->isAdmin() && isset($withdrawn) && $withdrawn->isNotEmpty())
    <div class="mt-10">
        <div class="flex items-center gap-2 mb-3">
            <h2 class="text-base font-semibold text-gray-500">退学者</h2>
            <span class="text-xs text-gray-400 bg-gray-100 rounded-full px-2 py-0.5">{{ $withdrawn->count() }}名</span>
        </div>
        <div class="bg-white rounded-xl shadow divide-y divide-gray-100">
            @foreach ($withdrawn as $student)
            <div class="flex items-center gap-4 px-4 py-3 opacity-70">
                @if ($student->face_image_path)
                    <img src="{{ Storage::url($student->face_image_path) }}" alt="{{ $student->name }}"
                         class="w-10 h-10 rounded-full object-cover grayscale">
                @else
                    <div class="w-10 h-10 rounded-full bg-gray-200 flex items-center justify-center text-gray-400">?</div>
                @endif
                <div class="flex-1 min-w-0">
                    <p class="font-medium text-gray-700 truncate">
                        {{ $student->name }}
                        <span class="text-xs text-red-500 ml-1">退学</span>
                    </p>
                    <p class="text-xs text-gray-400">
                        {{ $student->student_number }}
                        @if ($student->withdrawn_at) ・ {{ $student->withdrawn_at->format('Y/m/d') }} @endif
                        ・ 出席記録 {{ $student->attendance_records_count }}回
                    </p>
                </div>
                <div class="flex flex-col gap-1 text-right">
                    <a href="{{ route('students.show', $student) }}" class="text-xs text-indigo-600 hover:underline">詳細</a>
                    <a href="{{ route('students.edit', $student) }}" class="text-xs text-gray-500 hover:underline">復学/編集</a>
                </div>
            </div>
            @endforeach
        </div>
    </div>
    @endif

    {{-- 一括登録バー（選択モード時に下部に固定表示）。チェックボックスは form="bulk-form" で紐付く --}}
    <form id="bulk-form" method="POST" action="{{ route('students.bulkClass') }}"
          class="fixed bottom-0 left-0 right-0 z-40 hidden bg-white border-t border-gray-200 shadow-[0_-4px_12px_rgba(0,0,0,0.06)]">
        @csrf
        <div class="max-w-5xl mx-auto px-4 py-3 flex flex-wrap items-center gap-3">
            <span id="bar-count" class="text-sm font-medium text-gray-700 whitespace-nowrap">0名 選択中</span>

            <div class="flex items-center gap-2 flex-1 min-w-[240px]">
                <label class="text-sm text-gray-600 whitespace-nowrap">組</label>
                @include('students._class_select', ['selected' => null])
            </div>

            <button type="button" onclick="toggleSelectMode()"
                    class="text-sm text-gray-500 hover:bg-gray-100 px-3 py-2 rounded-lg">キャンセル</button>
            <button type="submit" id="bar-submit" disabled
                    class="bg-indigo-600 hover:bg-indigo-700 disabled:opacity-40 disabled:cursor-not-allowed text-white text-sm font-medium px-5 py-2 rounded-lg whitespace-nowrap">
                選択した学生に登録
            </button>
        </div>
    </form>
@endif

<script>
let selectMode = false;

function toggleSelectMode() {
    selectMode = !selectMode;
    document.querySelectorAll('.bulk-cb').forEach(cb => {
        cb.classList.toggle('hidden', !selectMode);
        if (!selectMode) cb.checked = false;
    });
    document.querySelectorAll('.student-card').forEach(c => {
        c.classList.toggle('cursor-pointer', selectMode);
        c.classList.toggle('cursor-default', !selectMode);
    });
    // 選択モード中は詳細/削除を隠してタップ選択に集中
    document.querySelectorAll('.card-actions').forEach(a => a.classList.toggle('hidden', selectMode));
    document.querySelectorAll('.select-only').forEach(b => b.classList.toggle('hidden', !selectMode));
    document.getElementById('select-hint').classList.toggle('hidden', !selectMode);
    document.getElementById('bulk-form').classList.toggle('hidden', !selectMode);

    const tgl = document.getElementById('select-toggle');
    tgl.textContent = selectMode ? '選択をやめる' : 'クラス一括登録';
    tgl.classList.toggle('bg-indigo-600', selectMode);
    tgl.classList.toggle('text-white', selectMode);
    tgl.classList.toggle('text-indigo-600', !selectMode);

    document.body.style.paddingBottom = selectMode ? '88px' : '';
    updateBar();
}

// 選択モード中、カード本体タップでチェックを切り替え（リンク等は除外）
document.querySelectorAll('.student-card').forEach(card => {
    card.addEventListener('click', e => {
        if (!selectMode) return;
        if (e.target.closest('a, button, .bulk-cb')) return;
        e.preventDefault();
        const cb = card.querySelector('.bulk-cb');
        cb.checked = !cb.checked;
        updateBar();
    });
});

function updateBar() {
    const checked = document.querySelectorAll('.bulk-cb:checked');
    document.getElementById('bar-count').textContent = checked.length + '名 選択中';
    document.getElementById('bar-submit').disabled = checked.length === 0;
    // 選択中カードを強調
    document.querySelectorAll('.student-card').forEach(card => {
        const on = card.querySelector('.bulk-cb').checked;
        card.classList.toggle('ring-2', on);
        card.classList.toggle('ring-indigo-400', on);
    });
}

function selectAll(on) {
    document.querySelectorAll('.bulk-cb').forEach(cb => cb.checked = on);
    updateBar();
}

function selectGroup(btn) {
    // ボタンの属する組のカードだけ選択
    const wrapper = btn.closest('.mb-8');
    wrapper.querySelectorAll('.bulk-cb').forEach(cb => cb.checked = true);
    updateBar();
}
</script>
@endsection
