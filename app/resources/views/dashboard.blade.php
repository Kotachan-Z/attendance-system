@extends('layouts.app')
@section('title', 'ダッシュボード')

@section('content')
<div class="mb-6 flex items-center justify-between">
    <h1 class="text-2xl font-bold text-gray-800">ダッシュボード — {{ now()->format('Y年n月j日') }}</h1>
    <a href="{{ route('sessions.create') }}"
       class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-4 py-2 rounded">
        + 授業開始
    </a>
</div>

<div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-8">
    <div class="bg-white rounded-xl shadow p-5">
        <p class="text-sm text-gray-500">登録学生数</p>
        <p class="text-3xl font-bold text-indigo-600">{{ $totalStudents }}</p>
    </div>
    <div class="bg-white rounded-xl shadow p-5">
        <p class="text-sm text-gray-500">本日出席人数</p>
        <p class="text-3xl font-bold text-green-600">{{ $todayPresent }}</p>
    </div>
    <div class="bg-white rounded-xl shadow p-5">
        <p class="text-sm text-gray-500">進行中セッション</p>
        <p class="text-3xl font-bold text-yellow-600">{{ $activeSessions->count() }}</p>
    </div>
</div>

@if ($activeSessions->isNotEmpty())
<div class="mb-8">
    <h2 class="text-lg font-semibold text-gray-700 mb-3">進行中のセッション</h2>
    <div class="space-y-2">
        @foreach ($activeSessions as $session)
        <div class="bg-yellow-50 border border-yellow-300 rounded-lg px-5 py-3 flex items-center justify-between">
            <div>
                <span class="font-medium text-gray-800">{{ $session->course->name }}</span>
                <span class="ml-3 text-sm text-gray-500">開始: {{ $session->started_at->format('H:i') }}</span>
            </div>
            <div class="flex gap-3">
                <a href="{{ route('sessions.show', $session) }}" class="text-sm text-indigo-600 hover:underline">詳細</a>
                <form method="POST" action="{{ route('sessions.end', $session) }}"
                      onsubmit="return confirm('セッションを終了しますか？')">
                    @csrf @method('PATCH')
                    <button type="submit" class="text-sm text-red-600 hover:underline">終了</button>
                </form>
            </div>
        </div>
        @endforeach
    </div>
</div>
@endif

<div>
    <h2 class="text-lg font-semibold text-gray-700 mb-3">本日のセッション</h2>
    @forelse ($todaySessions as $session)
    <div class="bg-white rounded-xl shadow mb-3 p-5">
        <div class="flex items-center justify-between mb-2">
            <span class="font-medium text-gray-800">{{ $session->course->name }}</span>
            @if ($session->isActive())
                <span class="text-xs bg-yellow-100 text-yellow-700 font-semibold px-2 py-0.5 rounded">進行中</span>
            @else
                <span class="text-xs bg-gray-100 text-gray-500 font-semibold px-2 py-0.5 rounded">終了</span>
            @endif
        </div>
        <p class="text-sm text-gray-500">出席: {{ $session->attendanceRecords->count() }}名</p>
        <a href="{{ route('sessions.show', $session) }}" class="text-sm text-indigo-600 hover:underline mt-1 inline-block">詳細を見る →</a>
    </div>
    @empty
    <p class="text-gray-500 text-sm">本日はまだセッションがありません。</p>
    @endforelse
</div>
@endsection
