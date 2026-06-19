@extends('layouts.app')
@section('title', $student->name . ' — 出席履歴')

@section('content')
<div class="mb-6 flex items-start gap-4">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">{{ $student->name }}</h1>
        <p class="text-sm text-gray-500">学籍番号: {{ $student->student_number }}</p>
        <p class="text-sm text-gray-500">総出席回数: {{ $records->count() }}回</p>
    </div>
    <a href="{{ route('students.edit', $student) }}"
       class="ml-auto text-sm bg-gray-100 hover:bg-gray-200 text-gray-700 px-3 py-1.5 rounded-lg">編集</a>
</div>

{{-- 登録顔写真 --}}
<div class="bg-white rounded-xl shadow p-5 mb-6">
    <div class="flex items-center justify-between mb-3">
        <h2 class="text-base font-semibold text-gray-700">登録顔写真 ({{ $student->faces->count() }}枚)</h2>
        <a href="{{ route('students.edit', $student) }}"
           class="text-xs text-indigo-600 hover:underline">写真を追加</a>
    </div>
    @if ($student->faces->isEmpty())
        <p class="text-sm text-gray-400">顔写真が登録されていません。</p>
    @else
    <div class="flex flex-wrap gap-3">
        @foreach ($student->faces as $face)
        <div class="relative group">
            <img src="{{ Storage::url($face->image_path) }}"
                 class="w-20 h-20 object-cover rounded-lg border-2 border-indigo-100"
                 title="{{ $face->label ?: '(ラベルなし)' }}">
            @if ($face->label)
                <span class="absolute bottom-0 left-0 right-0 text-center text-white text-xs bg-black/50 rounded-b-lg py-0.5">
                    {{ $face->label }}
                </span>
            @endif
            <form method="POST"
                  action="{{ route('students.faces.destroy', [$student, $face]) }}"
                  onsubmit="return confirm('この写真を削除しますか？')"
                  class="absolute -top-1 -right-1 hidden group-hover:block">
                @csrf @method('DELETE')
                <button type="submit"
                        class="w-5 h-5 bg-red-500 text-white rounded-full text-xs leading-none flex items-center justify-center">
                    ×
                </button>
            </form>
        </div>
        @endforeach
    </div>
    @endif
</div>

{{-- 出席履歴 --}}
<h2 class="text-lg font-semibold text-gray-700 mb-3">出席履歴</h2>

@forelse ($records as $record)
<div class="bg-white rounded-xl shadow p-4 mb-3 flex gap-4 items-center">
    @if ($record->captured_image_path)
        <img src="{{ Storage::url($record->captured_image_path) }}"
             class="w-16 h-16 object-cover rounded-lg border border-gray-200" alt="撮影画像">
    @else
        <div class="w-16 h-16 bg-gray-100 rounded-lg flex items-center justify-center text-gray-400 text-xs">なし</div>
    @endif
    <div>
        <p class="font-medium text-gray-800">{{ $record->attendanceSession->course->name }}</p>
        <p class="text-sm text-gray-500">{{ $record->attendanceSession->session_date->format('Y年n月j日') }}</p>
        <p class="text-xs text-gray-400">
            類似度: {{ $record->similarity_score !== null ? number_format((1 - $record->similarity_score) * 100, 1) . '%' : '—' }}
            &nbsp;/&nbsp;
            確認: {{ $record->verified_at?->format('H:i:s') ?? '—' }}
        </p>
    </div>
</div>
@empty
<p class="text-gray-500 text-sm">まだ出席記録がありません。</p>
@endforelse

<div class="mt-6">
    <a href="{{ route('students.index') }}" class="text-sm text-indigo-600 hover:underline">← 学生一覧に戻る</a>
</div>
@endsection
