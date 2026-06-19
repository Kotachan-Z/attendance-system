@extends('layouts.app')
@section('title', '検出ログ')

@section('content')
<div class="flex items-center justify-between mb-6">
    <h1 class="text-2xl font-bold text-gray-800">検出ログ</h1>
    <p class="text-sm text-gray-500">なりすまし疑い・未登録者として弾かれた検出の記録</p>
</div>

{{-- 絞り込みタブ --}}
@php
    $tabBase = 'px-4 py-2 rounded-lg text-sm font-medium border';
    $active  = 'bg-indigo-600 text-white border-indigo-600';
    $idle    = 'bg-white text-gray-700 border-gray-200 hover:bg-gray-50';
@endphp
<div class="flex flex-wrap gap-2 mb-5">
    <a href="{{ route('detections.index') }}"
       class="{{ $tabBase }} {{ $reason ? $idle : $active }}">
        すべて
    </a>
    <a href="{{ route('detections.index', ['reason' => 'spoofing']) }}"
       class="{{ $tabBase }} {{ $reason === 'spoofing' ? $active : $idle }}">
        なりすまし疑い
        <span class="ml-1 inline-block bg-red-100 text-red-700 rounded-full px-2 text-xs">{{ $counts['spoofing'] }}</span>
    </a>
    <a href="{{ route('detections.index', ['reason' => 'unknown']) }}"
       class="{{ $tabBase }} {{ $reason === 'unknown' ? $active : $idle }}">
        識別不能（未登録）
        <span class="ml-1 inline-block bg-amber-100 text-amber-700 rounded-full px-2 text-xs">{{ $counts['unknown'] }}</span>
    </a>
</div>

@if ($logs->isEmpty())
    <div class="bg-white rounded-xl shadow p-10 text-center text-gray-500">
        検出ログはまだありません。
    </div>
@else
    <div class="bg-white rounded-xl shadow overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-gray-600 text-left">
                <tr>
                    <th class="px-4 py-3 font-medium">撮影画像</th>
                    <th class="px-4 py-3 font-medium">種別</th>
                    <th class="px-4 py-3 font-medium">照合先 / 類似度</th>
                    <th class="px-4 py-3 font-medium">深度 std</th>
                    <th class="px-4 py-3 font-medium">授業</th>
                    <th class="px-4 py-3 font-medium">検出日時</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach ($logs as $log)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3">
                            @if ($log->captured_image_path)
                                <a href="{{ Storage::url($log->captured_image_path) }}" target="_blank">
                                    <img src="{{ Storage::url($log->captured_image_path) }}" alt="検出画像"
                                         class="w-16 h-16 object-cover rounded-lg border border-gray-200">
                                </a>
                            @else
                                <span class="text-gray-400 text-xs">画像なし</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            @if ($log->reason === 'spoofing')
                                <span class="inline-block bg-red-100 text-red-700 rounded-full px-2.5 py-0.5 text-xs font-bold">
                                    {{ $log->reasonLabel() }}
                                </span>
                            @else
                                <span class="inline-block bg-amber-100 text-amber-700 rounded-full px-2.5 py-0.5 text-xs font-bold">
                                    {{ $log->reasonLabel() }}
                                </span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            @if ($log->matchedStudent)
                                <span class="text-gray-800">{{ $log->matchedStudent->name }}</span>
                                <span class="text-gray-400 text-xs">（{{ $log->matchedStudent->student_number }}）</span>
                            @else
                                <span class="text-gray-400">—</span>
                            @endif
                            @if (! is_null($log->similarity_score))
                                <div class="text-xs text-gray-500">類似度 {{ number_format((1 - $log->similarity_score) * 100, 1) }}%</div>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-gray-700">
                            {{ is_null($log->depth_std_dev) ? '—' : number_format($log->depth_std_dev, 0) . ' mm' }}
                        </td>
                        <td class="px-4 py-3 text-gray-700">
                            {{ $log->attendanceSession?->course?->name ?? '—' }}
                        </td>
                        <td class="px-4 py-3 text-gray-500 whitespace-nowrap">
                            {{ $log->detected_at->format('Y/m/d H:i:s') }}
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $logs->links() }}
    </div>
@endif
@endsection
