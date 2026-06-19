@extends('layouts.app')
@section('title', '時間割')

@section('content')
<div class="mb-5 flex items-center justify-between">
    <h1 class="text-2xl font-bold text-gray-800">時間割</h1>
    <a href="{{ route('schedules.create') }}" class="text-sm text-indigo-600 hover:underline">単発・詳細登録 →</a>
</div>

@if ($courses->isEmpty())
    <div class="bg-amber-50 border border-amber-300 text-amber-800 rounded-lg px-4 py-3 text-sm mb-5">
        先に「授業」を登録してください。授業がないと時間割に割り当てできません。
    </div>
@endif

{{-- 学期（有効期間）バー --}}
<div class="bg-white rounded-xl shadow p-4 mb-5 flex flex-wrap items-center gap-3 text-sm">
    <span class="font-medium text-gray-700">期間</span>
    <input type="date" id="term-from" value="{{ $termFrom }}"
           class="border rounded-lg px-3 py-1.5 focus:outline-none focus:ring-2 focus:ring-indigo-400">
    <span class="text-gray-400">〜</span>
    <input type="date" id="term-to" value="{{ $termTo }}"
           class="border rounded-lg px-3 py-1.5 focus:outline-none focus:ring-2 focus:ring-indigo-400">
    <span class="text-xs text-gray-400">この期間の毎週でセッションが自動生成されます。セルを割り当てる前に設定してください。</span>
</div>

{{-- 時間割グリッド --}}
<div class="bg-white rounded-xl shadow overflow-x-auto">
    <table class="w-full border-collapse text-sm min-w-[640px]">
        <thead>
            <tr class="bg-gray-50 text-gray-600">
                <th class="w-20 px-2 py-3 border-b border-gray-100"></th>
                @foreach ($days as $dow)
                    <th class="px-2 py-3 border-b border-l border-gray-100 font-medium text-center">{{ $dowLabels[$dow] }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach ($periods as $p)
            <tr>
                <td class="px-2 py-3 text-center align-top border-b border-gray-100">
                    <div class="font-semibold text-gray-700">{{ $p['label'] }}</div>
                    <div class="text-[11px] text-gray-400">{{ $p['start'] }}</div>
                </td>
                @foreach ($days as $dow)
                    @php $cell = $grid[$dow . '|' . $p['start']] ?? null; @endphp
                    <td class="p-1.5 border-b border-l border-gray-100 align-top h-20">
                        @if ($cell)
                            <div class="relative group bg-indigo-50 border border-indigo-200 rounded-lg px-2 py-2 h-full">
                                <p class="text-indigo-800 font-medium leading-tight pr-4">{{ $cell->course->name }}</p>
                                <p class="text-[11px] text-indigo-400 mt-0.5">{{ $p['start'] }}–{{ $p['end'] }}</p>
                                <form method="POST" action="{{ route('schedules.destroy', $cell) }}"
                                      onsubmit="return confirm('「{{ $cell->course->name }}」をこのコマから外しますか？（生成済みセッションは残ります）')"
                                      class="absolute top-1 right-1">
                                    @csrf @method('DELETE')
                                    <button type="submit"
                                            class="w-5 h-5 hidden group-hover:flex items-center justify-center rounded-full bg-white/70 text-gray-400 hover:text-red-500 text-xs">×</button>
                                </form>
                            </div>
                        @else
                            <button type="button"
                                    onclick="openSlot({{ $dow }}, '{{ $dowLabels[$dow] }}', '{{ $p['label'] }}', '{{ $p['start'] }}', '{{ $p['end'] }}')"
                                    class="w-full h-full min-h-[64px] rounded-lg border border-dashed border-gray-200 text-gray-300 hover:border-indigo-300 hover:text-indigo-400 hover:bg-indigo-50/40 text-xl">
                                ＋
                            </button>
                        @endif
                    </td>
                @endforeach
            </tr>
            @endforeach
        </tbody>
    </table>
</div>

{{-- グリッド外（単発・特殊時間）のスケジュール --}}
@if (count($other))
<div class="mt-8">
    <h2 class="text-sm font-semibold text-gray-600 mb-3">その他のスケジュール（単発・時間割外）</h2>
    <div class="bg-white rounded-xl shadow divide-y divide-gray-100">
        @foreach ($other as $s)
        <div class="flex items-center justify-between px-4 py-3 text-sm">
            <div>
                <span class="font-medium text-gray-800">{{ $s->course->name }}</span>
                <span class="text-gray-500 ml-2">
                    @if ($s->type === 'weekly')
                        毎週{{ $s->dayOfWeekLabel() }}曜
                    @else
                        {{ optional($s->specific_date)->format('Y/n/j') }}
                    @endif
                    {{ \Illuminate\Support\Str::substr($s->start_time, 0, 5) }}–{{ \Illuminate\Support\Str::substr($s->end_time, 0, 5) }}
                </span>
            </div>
            <form method="POST" action="{{ route('schedules.destroy', $s) }}" onsubmit="return confirm('削除しますか？')">
                @csrf @method('DELETE')
                <button type="submit" class="text-xs text-red-500 hover:underline">削除</button>
            </form>
        </div>
        @endforeach
    </div>
</div>
@endif

{{-- 授業割り当てモーダル --}}
<div id="slot-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40 p-4">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-sm p-6">
        <h3 class="text-lg font-bold text-gray-800 mb-1">授業を割り当て</h3>
        <p id="slot-context" class="text-sm text-gray-500 mb-4"></p>

        <form method="POST" action="{{ route('schedules.slot') }}" onsubmit="syncTerm()">
            @csrf
            <input type="hidden" name="day_of_week"     id="slot-dow">
            <input type="hidden" name="start_time"      id="slot-start">
            <input type="hidden" name="end_time"        id="slot-end">
            <input type="hidden" name="effective_from"  id="slot-from">
            <input type="hidden" name="effective_until" id="slot-until">

            <label class="block text-sm font-medium text-gray-700 mb-1">授業</label>
            <select name="course_id" required
                    class="w-full border rounded-lg px-3 py-2 text-sm mb-5 focus:outline-none focus:ring-2 focus:ring-indigo-400">
                <option value="">選択してください</option>
                @foreach ($courses as $course)
                    <option value="{{ $course->id }}">{{ $course->name }}</option>
                @endforeach
            </select>

            <div class="flex gap-3 justify-end">
                <button type="button" onclick="closeSlot()"
                        class="px-4 py-2 text-sm text-gray-600 hover:bg-gray-100 rounded-lg">キャンセル</button>
                <button type="submit"
                        class="px-5 py-2 text-sm font-medium bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg">割り当てる</button>
            </div>
        </form>
    </div>
</div>

<script>
function openSlot(dow, dowLabel, periodLabel, start, end) {
    document.getElementById('slot-dow').value   = dow;
    document.getElementById('slot-start').value = start;
    document.getElementById('slot-end').value   = end;
    document.getElementById('slot-context').textContent = dowLabel + '曜日 ' + periodLabel + '（' + start + '–' + end + '）';
    const m = document.getElementById('slot-modal');
    m.classList.remove('hidden');
    m.classList.add('flex');
}
function closeSlot() {
    const m = document.getElementById('slot-modal');
    m.classList.add('hidden');
    m.classList.remove('flex');
}
function syncTerm() {
    document.getElementById('slot-from').value  = document.getElementById('term-from').value;
    document.getElementById('slot-until').value = document.getElementById('term-to').value;
}
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeSlot(); });
document.getElementById('slot-modal').addEventListener('click', e => {
    if (e.target.id === 'slot-modal') closeSlot();
});
</script>
@endsection
