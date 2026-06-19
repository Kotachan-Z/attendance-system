@extends('layouts.app')
@section('title', $course->name . ' — 出欠表')

@section('content')
@php
    // ステータスごとの見た目（記号・色）
    $styles = [
        'present' => ['mark' => '○', 'cell' => 'bg-green-50 text-green-700',  'label' => '出席'],
        'late'    => ['mark' => '△', 'cell' => 'bg-amber-50 text-amber-700',  'label' => '遅刻'],
        'absent'  => ['mark' => '×', 'cell' => 'bg-red-50 text-red-600',      'label' => '欠席'],
    ];
@endphp

<div class="mb-5 flex flex-wrap items-center justify-between gap-3">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">{{ $course->name }} <span class="text-base font-normal text-gray-400">出欠表</span></h1>
        <p class="text-sm text-gray-500 mt-1">履修 {{ $students->count() }}名 ／ 授業日 {{ $sessions->count() }}日</p>
    </div>
    <a href="{{ route('courses.index') }}" class="text-sm text-indigo-600 hover:underline">← 授業一覧</a>
</div>

{{-- 凡例 --}}
<div class="flex flex-wrap items-center gap-4 text-xs text-gray-600 mb-4">
    @foreach ($styles as $s)
        <span class="inline-flex items-center gap-1">
            <span class="inline-flex w-5 h-5 items-center justify-center rounded {{ $s['cell'] }} font-bold">{{ $s['mark'] }}</span>
            {{ $s['label'] }}
        </span>
    @endforeach
    <span class="inline-flex items-center gap-1">
        <span class="inline-flex w-5 h-5 items-center justify-center rounded bg-gray-50 text-gray-300">・</span>
        記録なし
    </span>
    <span class="inline-flex items-center gap-1">
        <span class="relative inline-flex w-5 h-5 items-center justify-center rounded bg-gray-100 text-gray-500"><span class="absolute -top-1 -right-1 w-2 h-2 bg-indigo-500 rounded-full"></span></span>
        手動修正
    </span>
    <span class="text-gray-400">｜セルをタップで修正できます</span>
</div>

@if ($students->isEmpty())
    <div class="bg-amber-50 border border-amber-300 text-amber-800 rounded-lg px-4 py-3 text-sm">
        この授業にはまだ履修学生がいません。
        <a href="{{ route('courses.edit', $course) }}" class="underline">履修登録</a>から学生を割り当ててください。
    </div>
@elseif ($sessions->isEmpty())
    <div class="bg-amber-50 border border-amber-300 text-amber-800 rounded-lg px-4 py-3 text-sm">
        まだ授業日（セッション）がありません。時間割で割り当てると自動生成されます。
    </div>
@else
<div class="bg-white rounded-xl shadow overflow-x-auto">
    <table class="border-collapse text-sm">
        <thead>
            <tr class="bg-gray-50 text-gray-600">
                <th class="sticky left-0 z-10 bg-gray-50 px-3 py-2 text-left border-b border-gray-100 min-w-[140px]">学生</th>
                @foreach ($sessions as $sess)
                    <th class="px-2 py-2 border-b border-l border-gray-100 font-medium text-center whitespace-nowrap">
                        <div>{{ $sess->session_date->format('n/j') }}</div>
                        <div class="text-[10px] text-gray-400">({{ ['日','月','火','水','木','金','土'][$sess->session_date->dayOfWeek] }})</div>
                    </th>
                @endforeach
                <th class="px-3 py-2 border-b border-l border-gray-100 text-center whitespace-nowrap">出/遅/欠</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($students as $student)
            <tr class="hover:bg-indigo-50/30">
                <td class="sticky left-0 z-10 bg-white px-3 py-2 border-b border-gray-100">
                    <a href="{{ route('students.show', $student) }}" class="font-medium text-gray-800 hover:text-indigo-600">{{ $student->name }}</a>
                    @if ($student->class_name)
                        <div class="text-[11px] text-gray-400">{{ $student->class_name }}</div>
                    @endif
                </td>
                @foreach ($sessions as $sess)
                    @php
                        $cell   = $statusMap[$sess->id][$student->id] ?? null;
                        $st     = $cell['status'] ?? null;
                        $note   = $cell['note'] ?? null;
                        $manual = $cell['manual'] ?? false;
                        $title  = ($st && isset($styles[$st]) ? $styles[$st]['label'] : '記録なし')
                                . ($manual ? '（手動）' : '')
                                . ($note ? '：' . $note : '');
                    @endphp
                    <td class="px-2 py-2 border-b border-l border-gray-100 text-center">
                        <button type="button"
                                class="att-cell relative inline-flex w-7 h-7 items-center justify-center rounded font-bold hover:ring-2 hover:ring-indigo-300 transition
                                       {{ $st && isset($styles[$st]) ? $styles[$st]['cell'] : 'text-gray-300 hover:bg-gray-50' }}"
                                title="{{ $title }}"
                                data-session="{{ $sess->id }}"
                                data-student="{{ $student->id }}"
                                data-name="{{ $student->name }}"
                                data-date="{{ $sess->session_date->format('n月j日') }}"
                                data-status="{{ $st ?? '' }}"
                                data-note="{{ $note }}">
                            {{ $st && isset($styles[$st]) ? $styles[$st]['mark'] : '・' }}
                            @if ($manual)
                                <span class="absolute -top-1 -right-1 w-2 h-2 bg-indigo-500 rounded-full"></span>
                            @endif
                        </button>
                    </td>
                @endforeach
                <td class="px-3 py-2 border-b border-l border-gray-100 text-center whitespace-nowrap text-xs">
                    <span class="text-green-600 font-semibold">{{ $summary[$student->id]['present'] }}</span><span class="text-gray-300">/</span><span class="text-amber-600 font-semibold">{{ $summary[$student->id]['late'] }}</span><span class="text-gray-300">/</span><span class="text-red-500 font-semibold">{{ $summary[$student->id]['absent'] }}</span>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
<p class="text-xs text-gray-400 mt-3">※ 列は「授業があった日」のみ表示しています（本日まで）。記録なしは、まだ集計されていない日です。</p>

{{-- 出欠修正モーダル --}}
<div id="edit-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40 p-4">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-sm p-6">
        <h3 class="text-lg font-bold text-gray-800 mb-1">出欠を修正</h3>
        <p id="edit-context" class="text-sm text-gray-500 mb-4"></p>

        <form id="edit-form" method="POST">
            @csrf
            <div class="grid grid-cols-3 gap-2 mb-4" id="status-choices">
                <label class="status-opt cursor-pointer">
                    <input type="radio" name="status" value="present" class="peer sr-only">
                    <span class="block text-center py-2 rounded-lg border text-sm peer-checked:bg-green-100 peer-checked:border-green-400 peer-checked:text-green-700 hover:bg-gray-50">○ 出席</span>
                </label>
                <label class="status-opt cursor-pointer">
                    <input type="radio" name="status" value="late" class="peer sr-only">
                    <span class="block text-center py-2 rounded-lg border text-sm peer-checked:bg-amber-100 peer-checked:border-amber-400 peer-checked:text-amber-700 hover:bg-gray-50">△ 遅刻</span>
                </label>
                <label class="status-opt cursor-pointer">
                    <input type="radio" name="status" value="absent" class="peer sr-only">
                    <span class="block text-center py-2 rounded-lg border text-sm peer-checked:bg-red-100 peer-checked:border-red-400 peer-checked:text-red-600 hover:bg-gray-50">× 欠席</span>
                </label>
            </div>

            <label class="block text-sm font-medium text-gray-700 mb-1">理由・メモ <span class="text-gray-400 font-normal text-xs">（任意。例: 公欠）</span></label>
            <input type="text" name="note" id="edit-note" maxlength="255" placeholder="公欠 など"
                   class="w-full border rounded-lg px-3 py-2 text-sm mb-5 focus:outline-none focus:ring-2 focus:ring-indigo-400">

            <div class="flex gap-3 justify-end">
                <button type="button" onclick="closeEdit()"
                        class="px-4 py-2 text-sm text-gray-600 hover:bg-gray-100 rounded-lg">キャンセル</button>
                <button type="submit"
                        class="px-5 py-2 text-sm font-medium bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg">保存</button>
            </div>
        </form>
    </div>
</div>

<script>
// action URL テンプレート（SID/STID を置換して使う）
const ACTION_TPL = "{{ url('sessions') }}/SID/students/STID/status";

document.querySelectorAll('.att-cell').forEach(btn => {
    btn.addEventListener('click', () => {
        const d = btn.dataset;
        document.getElementById('edit-context').textContent = d.name + ' ／ ' + d.date;
        document.getElementById('edit-form').action =
            ACTION_TPL.replace('SID', d.session).replace('STID', d.student);

        // 現在のステータスを選択（未記録なら未選択）
        document.querySelectorAll('#status-choices input').forEach(i => i.checked = (i.value === d.status));
        document.getElementById('edit-note').value = d.note || '';

        const m = document.getElementById('edit-modal');
        m.classList.remove('hidden');
        m.classList.add('flex');
    });
});

function closeEdit() {
    const m = document.getElementById('edit-modal');
    m.classList.add('hidden');
    m.classList.remove('flex');
}
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeEdit(); });
document.getElementById('edit-modal').addEventListener('click', e => {
    if (e.target.id === 'edit-modal') closeEdit();
});
// ステータス未選択のまま保存しようとしたら出席を既定にしない（必須）
document.getElementById('edit-form').addEventListener('submit', e => {
    if (![...document.querySelectorAll('#status-choices input')].some(i => i.checked)) {
        e.preventDefault();
        alert('出席・遅刻・欠席のいずれかを選んでください。');
    }
});
</script>
@endif
@endsection
