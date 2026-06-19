@extends('layouts.app')
@section('title', '授業開始')

@section('content')
<div class="max-w-lg mx-auto">
    <h1 class="text-2xl font-bold text-gray-800 mb-6">授業セッション開始</h1>

    <form method="POST" action="{{ route('sessions.store') }}"
          class="bg-white rounded-xl shadow p-6 space-y-5">
        @csrf

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">授業</label>
            <select name="course_id" required
                    class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400 @error('course_id') border-red-400 @enderror">
                <option value="">選択してください</option>
                @foreach ($courses as $course)
                    <option value="{{ $course->id }}" {{ old('course_id') == $course->id ? 'selected' : '' }}>
                        {{ $course->name }}
                    </option>
                @endforeach
            </select>
            @error('course_id') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">授業日</label>
            <input type="date" name="session_date"
                   value="{{ old('session_date', today()->format('Y-m-d')) }}" required
                   class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400 @error('session_date') border-red-400 @enderror">
            @error('session_date') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
        </div>

        @if ($courses->isEmpty())
            <p class="text-yellow-600 text-sm bg-yellow-50 border border-yellow-200 rounded px-3 py-2">
                授業がまだ登録されていません。先に
                <a href="{{ route('courses.index') }}" class="underline">授業を作成</a>してください。
            </p>
        @endif

        <div class="flex gap-3 pt-2">
            <button type="submit" @if($courses->isEmpty()) disabled @endif
                    class="bg-indigo-600 hover:bg-indigo-700 disabled:opacity-40 text-white text-sm font-medium px-5 py-2 rounded-lg">
                開始
            </button>
            <a href="{{ route('sessions.index') }}"
               class="bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm font-medium px-5 py-2 rounded-lg">
                キャンセル
            </a>
        </div>
    </form>
</div>
@endsection
