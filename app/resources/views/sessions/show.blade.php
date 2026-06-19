@extends('layouts.app')
@section('title', $session->course->name . ' — 出席詳細')

@section('content')
<div class="mb-6 flex items-center justify-between">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">{{ $session->course->name }}</h1>
        <p class="text-sm text-gray-500">
            {{ $session->session_date->format('Y年n月j日') }}
            {{ $session->started_at->format('H:i') }} 開始
            @if ($session->ended_at)
                — {{ $session->ended_at->format('H:i') }} 終了
            @else
                — <span class="text-yellow-600 font-medium">進行中</span>
            @endif
        </p>
        <p class="text-sm text-gray-500">出席: {{ $records->count() }}名</p>
        @if ($session->scheduled_start_at)
        <p class="text-xs text-gray-400 mt-1">
            予定: {{ $session->scheduled_start_at->format('H:i') }}〜{{ optional($session->scheduled_end_at)->format('H:i') }}
            ／開始から{{ $session->late_threshold_minutes }}分まで遅刻・以降は欠席扱い
        </p>
        @endif
    </div>
    @if ($session->isActive() && Auth::user()->isAdmin())
    <form method="POST" action="{{ route('sessions.end', $session) }}"
          onsubmit="return confirm('セッションを終了しますか？')">
        @csrf @method('PATCH')
        <button type="submit"
                class="bg-red-500 hover:bg-red-600 text-white text-sm font-medium px-4 py-2 rounded">
            セッション終了
        </button>
    </form>
    @endif
</div>

@if ($records->isEmpty())
    <p class="text-gray-500 text-sm bg-white rounded-xl shadow p-6">まだ出席者がいません。</p>
@else
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
    @foreach ($records as $record)
    <div class="bg-white rounded-xl shadow p-4 flex gap-3 items-center">
        <div class="flex-shrink-0">
            @if ($record->captured_image_path)
                <img src="{{ Storage::url($record->captured_image_path) }}"
                     class="w-16 h-16 object-cover rounded-lg border border-gray-200"
                     alt="撮影画像">
            @else
                <div class="w-16 h-16 bg-gray-100 rounded-lg flex items-center justify-center text-gray-400 text-xs">なし</div>
            @endif
        </div>
        <div class="min-w-0">
            <div class="flex items-center gap-2">
                <p class="font-semibold text-gray-800 truncate">{{ $record->student->name }}</p>
                @php
                    $badge = [
                        'present' => 'bg-green-100 text-green-700',
                        'late'    => 'bg-amber-100 text-amber-700',
                        'absent'  => 'bg-red-100 text-red-700',
                    ][$record->status] ?? 'bg-gray-100 text-gray-600';
                @endphp
                <span class="text-xs font-semibold px-2 py-0.5 rounded {{ $badge }}">{{ $record->statusLabel() }}</span>
            </div>
            <p class="text-xs text-gray-500">{{ $record->student->student_number }}</p>
            <p class="text-xs text-gray-400 mt-0.5">
                類似度: {{ $record->similarity_score ? number_format((1 - $record->similarity_score) * 100, 1) . '%' : '—' }}
            </p>
            <p class="text-xs text-gray-400">{{ $record->verified_at?->format('H:i:s') }}</p>
        </div>
    </div>
    @endforeach
</div>
@endif

<div class="mt-6">
    <a href="{{ route('sessions.index') }}" class="text-sm text-indigo-600 hover:underline">← セッション一覧に戻る</a>
</div>
@endsection
