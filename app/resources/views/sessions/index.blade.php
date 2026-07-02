@extends('layouts.app')
@section('title', '出席記録')

@section('content')
<div class="mb-6 flex items-center justify-between">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">出席記録</h1>
        <p class="text-sm text-gray-500 mt-0.5">授業セッションごとの出席状況の一覧</p>
    </div>
    @if (Auth::user()->isAdmin())
    <a href="{{ route('sessions.create') }}"
       class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-4 py-2 rounded">
        + 授業開始（手動）
    </a>
    @endif
</div>

<div class="bg-white rounded-xl shadow overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-gray-50 text-gray-600 text-left">
            <tr>
                <th class="px-4 py-3">授業名</th>
                <th class="px-4 py-3">日付</th>
                <th class="px-4 py-3">開始</th>
                <th class="px-4 py-3">終了</th>
                <th class="px-4 py-3">出席数</th>
                <th class="px-4 py-3">状態</th>
                <th class="px-4 py-3"></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            @forelse ($sessions as $session)
            <tr class="hover:bg-gray-50">
                <td class="px-4 py-3 font-medium text-gray-800">{{ $session->course->name }}</td>
                <td class="px-4 py-3 text-gray-600">{{ $session->session_date->format('Y/m/d') }}</td>
                <td class="px-4 py-3 text-gray-600">{{ $session->started_at->format('H:i') }}</td>
                <td class="px-4 py-3 text-gray-600">{{ $session->ended_at?->format('H:i') ?? '—' }}</td>
                <td class="px-4 py-3 text-gray-600">{{ $session->attendance_records_count }}名</td>
                <td class="px-4 py-3">
                    @if ($session->isActive())
                        <span class="text-xs bg-yellow-100 text-yellow-700 font-semibold px-2 py-0.5 rounded">進行中</span>
                    @else
                        <span class="text-xs bg-gray-100 text-gray-500 font-semibold px-2 py-0.5 rounded">終了</span>
                    @endif
                </td>
                <td class="px-4 py-3">
                    <a href="{{ route('sessions.show', $session) }}" class="text-indigo-600 hover:underline">詳細</a>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="7" class="px-4 py-6 text-center text-gray-400">セッションがありません</td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-4">{{ $sessions->links() }}</div>
@endsection
