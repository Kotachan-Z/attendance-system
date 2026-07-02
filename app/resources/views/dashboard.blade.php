@extends('layouts.app')
@section('title', 'ホーム')

@section('content')
<div class="mb-6 flex flex-wrap items-center justify-between gap-3">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">ホーム</h1>
        <p class="text-sm text-gray-500 mt-0.5">{{ now()->format('Y年n月j日') }}（{{ ['日','月','火','水','木','金','土'][now()->dayOfWeek] }}）</p>
    </div>
    @if (Auth::user()->isAdmin())
    <a href="{{ route('sessions.create') }}"
       class="bg-indigo-500 hover:bg-indigo-600 text-white text-sm font-medium px-4 py-2 rounded-lg shadow-sm">
        + 授業を開始（手動）
    </a>
    @endif
</div>

{{-- 進行中の授業（最重要情報として最上部に大きく表示） --}}
@if ($activeSessions->isNotEmpty())
<div class="mb-8">
    @foreach ($activeSessions as $session)
    <div class="bg-white border-l-4 border-emerald-300 rounded-xl shadow-sm mb-3 p-5 sm:p-6">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div class="flex items-center gap-4">
                <span class="relative flex h-3 w-3">
                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-300 opacity-60"></span>
                    <span class="relative inline-flex rounded-full h-3 w-3 bg-emerald-400"></span>
                </span>
                <div>
                    <p class="text-xs text-emerald-600 font-semibold mb-0.5">進行中の授業</p>
                    <p class="text-xl font-bold text-gray-800">{{ $session->course->name }}</p>
                    <p class="text-sm text-gray-500 mt-0.5">
                        開始 {{ $session->started_at->format('H:i') }}
                        ・ 現在の出席 <span class="font-semibold text-gray-700">{{ $session->attendance_records_count }}名</span>
                    </p>
                </div>
            </div>
            <div class="flex items-center gap-3">
                <a href="{{ route('sessions.show', $session) }}"
                   class="bg-emerald-500 hover:bg-emerald-600 text-white text-sm font-medium px-4 py-2 rounded-lg">
                    出席状況を見る →
                </a>
                @if (Auth::user()->isAdmin())
                <form method="POST" action="{{ route('sessions.end', $session) }}"
                      onsubmit="return confirm('セッションを終了しますか？未検出の履修者は欠席として記録されます。')">
                    @csrf @method('PATCH')
                    <button type="submit"
                            class="text-sm text-rose-500 hover:text-rose-600 hover:bg-rose-50 border border-rose-200 px-3 py-2 rounded-lg">
                        終了する
                    </button>
                </form>
                @endif
            </div>
        </div>
    </div>
    @endforeach
</div>
@endif

{{-- サマリーカード --}}
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
        <p class="text-sm text-gray-500">在籍学生数</p>
        <p class="text-3xl font-bold text-indigo-500 mt-1">{{ $totalStudents }}<span class="text-base font-medium text-gray-400 ml-1">名</span></p>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
        <p class="text-sm text-gray-500">本日の出席者</p>
        <p class="text-3xl font-bold text-emerald-500 mt-1">{{ $todayPresent }}<span class="text-base font-medium text-gray-400 ml-1">名</span></p>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
        <p class="text-sm text-gray-500">本日の授業</p>
        <p class="text-3xl font-bold text-gray-600 mt-1">{{ $todaySessions->count() }}<span class="text-base font-medium text-gray-400 ml-1">コマ</span></p>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
        <p class="text-sm text-gray-500">進行中</p>
        <p class="text-3xl font-bold {{ $activeSessions->isNotEmpty() ? 'text-emerald-500' : 'text-gray-400' }} mt-1">{{ $activeSessions->count() }}<span class="text-base font-medium text-gray-400 ml-1">件</span></p>
    </div>
</div>

{{-- 本日の授業一覧（コンパクトな表） --}}
<div>
    <div class="flex items-center justify-between mb-3">
        <h2 class="text-lg font-semibold text-gray-700">本日の授業</h2>
        <a href="{{ route('sessions.index') }}" class="text-sm text-indigo-600 hover:underline">過去の記録を見る →</a>
    </div>

    @if ($todaySessions->isNotEmpty())
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-gray-600 text-left">
                <tr>
                    <th class="px-4 py-3">授業名</th>
                    <th class="px-4 py-3">開始</th>
                    <th class="px-4 py-3">出席</th>
                    <th class="px-4 py-3">状態</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach ($todaySessions as $session)
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 font-medium text-gray-800">{{ $session->course->name }}</td>
                    <td class="px-4 py-3 text-gray-600">{{ $session->started_at->format('H:i') }}</td>
                    <td class="px-4 py-3 text-gray-600">{{ $session->attendanceRecords->count() }}名</td>
                    <td class="px-4 py-3">
                        @if ($session->isActive())
                            <span class="text-xs bg-emerald-50 text-emerald-600 font-semibold px-2 py-0.5 rounded">進行中</span>
                        @else
                            <span class="text-xs bg-gray-100 text-gray-500 font-semibold px-2 py-0.5 rounded">終了</span>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-right">
                        <a href="{{ route('sessions.show', $session) }}" class="text-indigo-600 hover:underline">詳細 →</a>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @else
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-8 text-center">
        <p class="text-gray-500 text-sm mb-2">本日の授業はまだありません。</p>
        @if (Auth::user()->isAdmin())
        <p class="text-gray-400 text-xs">
            <a href="{{ route('schedules.index') }}" class="text-indigo-600 hover:underline">時間割</a>を設定すると、期間内の授業セッションが毎日自動で生成されます。
        </p>
        @endif
    </div>
    @endif
</div>
@endsection
