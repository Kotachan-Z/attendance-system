@extends('layouts.app')
@section('title', '授業一覧')

@section('content')
<div class="mb-6 flex items-center justify-between">
    <h1 class="text-2xl font-bold text-gray-800">授業一覧</h1>
</div>

<div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-8">
    @forelse ($courses as $course)
    <div class="bg-white rounded-xl shadow p-5 flex justify-between items-start">
        <div>
            <p class="font-semibold text-gray-800">{{ $course->name }}</p>
            @if ($course->description)
                <p class="text-sm text-gray-500 mt-1">{{ $course->description }}</p>
            @endif
            <p class="text-xs text-gray-400 mt-1">
                履修: {{ $course->students_count }}名
                ｜セッション数: {{ $course->attendance_sessions_count }}
                ｜遅刻判定: 開始から{{ $course->late_threshold_minutes }}分
            </p>
            <a href="{{ route('courses.attendance', $course) }}"
               class="inline-block mt-2 text-xs font-medium text-indigo-600 hover:underline">出欠表を見る →</a>
        </div>
        @if (Auth::user()->isAdmin())
        <div class="flex flex-col items-end gap-1">
            <a href="{{ route('courses.edit', $course) }}" class="text-xs text-indigo-600 hover:underline">履修登録</a>
            <form method="POST" action="{{ route('courses.destroy', $course) }}"
                  onsubmit="return confirm('授業を削除しますか？（関連セッションもすべて削除されます）')">
                @csrf @method('DELETE')
                <button type="submit" class="text-xs text-red-500 hover:underline">削除</button>
            </form>
        </div>
        @endif
    </div>
    @empty
    <p class="text-gray-500 col-span-2">授業が登録されていません。</p>
    @endforelse
</div>

@if (Auth::user()->isAdmin())
<div class="bg-white rounded-xl shadow p-6 max-w-md">
    <h2 class="text-lg font-semibold text-gray-700 mb-4">授業を追加</h2>
    <form method="POST" action="{{ route('courses.store') }}" class="space-y-4">
        @csrf
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">授業名</label>
            <input type="text" name="name" value="{{ old('name') }}" required
                   class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400 @error('name') border-red-400 @enderror">
            @error('name') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">説明（任意）</label>
            <textarea name="description" rows="2"
                      class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400">{{ old('description') }}</textarea>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">
                遅刻とみなす猶予（分）
                <span class="text-gray-400 font-normal text-xs ml-1">開始からこの分数を過ぎると欠席扱い</span>
            </label>
            <input type="number" name="late_threshold_minutes" value="{{ old('late_threshold_minutes', 20) }}"
                   min="0" max="120"
                   class="w-32 border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400">
            @error('late_threshold_minutes') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
        </div>
        <button type="submit"
                class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-5 py-2 rounded-lg">
            追加
        </button>
    </form>
</div>
@else
<p class="text-sm text-gray-400">授業の追加・編集は管理者のみ可能です。</p>
@endif
@endsection
