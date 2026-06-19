@extends('layouts.student')
@section('title', 'マイページ')

@php
    $statusLabel = ['present' => '出席', 'late' => '遅刻', 'absent' => '欠席'];
    $statusClass = [
        'present' => 'bg-green-100 text-green-700',
        'late'    => 'bg-yellow-100 text-yellow-700',
        'absent'  => 'bg-red-100 text-red-700',
    ];
@endphp

@section('content')
<div class="mb-6">
    <h1 class="text-2xl font-bold text-gray-800">{{ $student->name }} さん</h1>
    <p class="text-sm text-gray-500 mt-1">
        学籍番号: {{ $student->student_number }}
        @if (filled($student->class_name))
            <span class="ml-2">/ クラス: {{ $student->class_name }}</span>
        @endif
    </p>
</div>

{{-- 時間割 --}}
<section class="mb-10">
    <h2 class="text-base font-semibold text-gray-700 mb-3">時間割</h2>
    <div class="bg-white rounded-xl shadow overflow-x-auto">
        <table class="w-full text-sm border-collapse">
            <thead>
                <tr class="bg-gray-50 text-gray-500 text-xs">
                    <th class="px-3 py-2 border-b border-gray-100 w-16"></th>
                    @foreach ($days as $dow)
                        <th class="px-3 py-2 border-b border-gray-100 text-center">{{ $dowLabels[$dow] ?? '' }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($periods as $period)
                <tr>
                    <td class="px-3 py-3 border-b border-gray-100 text-center align-top">
                        <div class="font-medium text-gray-600">{{ $period['label'] }}</div>
                        <div class="text-[10px] text-gray-400">{{ $period['start'] }}<br>{{ $period['end'] }}</div>
                    </td>
                    @foreach ($days as $dow)
                        @php $cell = $grid[$dow . '|' . $period['start']] ?? null; @endphp
                        <td class="px-2 py-3 border-b border-gray-100 align-top">
                            @if ($cell)
                                <div class="bg-emerald-50 border border-emerald-200 rounded-lg px-2 py-1.5">
                                    <div class="text-xs font-medium text-emerald-800">{{ $cell->course->name }}</div>
                                </div>
                            @endif
                        </td>
                    @endforeach
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @if (! empty($other))
        <div class="mt-3 text-xs text-gray-500">
            <p class="font-medium mb-1">グリッド外のスケジュール</p>
            <ul class="list-disc list-inside space-y-0.5">
                @foreach ($other as $s)
                    <li>{{ $s->course->name }}（{{ $dowLabels[$s->day_of_week] ?? '' }} {{ \Illuminate\Support\Str::substr($s->start_time, 0, 5) }}〜）</li>
                @endforeach
            </ul>
        </div>
    @endif
</section>

{{-- 出欠状況 --}}
<section>
    <h2 class="text-base font-semibold text-gray-700 mb-3">出欠状況</h2>

    @if (empty($attendance))
        <p class="text-gray-500 text-sm">履修している授業がありません。</p>
    @else
        <div class="space-y-6">
            @foreach ($attendance as $item)
            <div class="bg-white rounded-xl shadow overflow-hidden">
                <div class="px-5 py-3 border-b border-gray-100 flex flex-wrap items-center justify-between gap-2">
                    <h3 class="font-semibold text-gray-800">{{ $item['course']->name }}</h3>
                    <div class="flex items-center gap-2 text-xs">
                        <span class="bg-green-100 text-green-700 rounded-full px-2 py-0.5">出席 {{ $item['counts']['present'] }}</span>
                        <span class="bg-yellow-100 text-yellow-700 rounded-full px-2 py-0.5">遅刻 {{ $item['counts']['late'] }}</span>
                        <span class="bg-red-100 text-red-700 rounded-full px-2 py-0.5">欠席 {{ $item['counts']['absent'] }}</span>
                        <span class="text-gray-400">/ 全{{ $item['total'] }}回</span>
                    </div>
                </div>

                @if ($item['total'] === 0)
                    <p class="px-5 py-4 text-sm text-gray-400">まだ授業（セッション）がありません。</p>
                @else
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 text-gray-500 text-xs">
                            <tr>
                                <th class="text-left px-5 py-2">日付</th>
                                <th class="text-left px-5 py-2">出欠</th>
                                <th class="text-left px-5 py-2">備考</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($item['rows'] as $row)
                            <tr>
                                <td class="px-5 py-2.5 text-gray-700">
                                    {{ \Illuminate\Support\Carbon::parse($row['date'])->format('Y/m/d') }}
                                </td>
                                <td class="px-5 py-2.5">
                                    @if ($row['status'] && isset($statusLabel[$row['status']]))
                                        <span class="inline-block text-xs rounded-full px-2 py-0.5 {{ $statusClass[$row['status']] }}">
                                            {{ $statusLabel[$row['status']] }}
                                        </span>
                                        @if ($row['manual'])
                                            <span class="ml-1 text-[10px] text-gray-400" title="手動修正済み">●手動</span>
                                        @endif
                                    @else
                                        <span class="text-gray-300">—</span>
                                    @endif
                                </td>
                                <td class="px-5 py-2.5 text-gray-500 text-xs">{{ $row['note'] }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
            @endforeach
        </div>
    @endif
</section>
@endsection
