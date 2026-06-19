@extends('layouts.app')
@section('title', 'スケジュール追加')

@section('content')
<div class="max-w-lg mx-auto">
    <h1 class="text-2xl font-bold text-gray-800 mb-6">スケジュール追加</h1>

    @if ($errors->any())
        <div class="bg-red-50 border border-red-300 text-red-700 rounded-lg px-4 py-3 text-sm mb-4">
            <ul class="list-disc list-inside">
                @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('schedules.store') }}"
          class="bg-white rounded-xl shadow p-6 space-y-5">
        @csrf

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">授業</label>
            <select name="course_id" required
                    class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400">
                <option value="">選択してください</option>
                @foreach ($courses as $course)
                    <option value="{{ $course->id }}" @selected(old('course_id') == $course->id)>{{ $course->name }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">種別</label>
            <div class="flex gap-4 text-sm">
                <label class="flex items-center gap-2">
                    <input type="radio" name="type" value="weekly" onchange="toggleType()"
                           @checked(old('type', 'weekly') === 'weekly')> 毎週（曜日繰り返し）
                </label>
                <label class="flex items-center gap-2">
                    <input type="radio" name="type" value="onetime" onchange="toggleType()"
                           @checked(old('type') === 'onetime')> 単発（特定日）
                </label>
            </div>
        </div>

        {{-- weekly --}}
        <div id="weekly-fields" class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">曜日</label>
                <select name="day_of_week"
                        class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400">
                    @foreach (['日','月','火','水','木','金','土'] as $i => $label)
                        <option value="{{ $i }}" @selected(old('day_of_week') == $i)>{{ $label }}曜日</option>
                    @endforeach
                </select>
            </div>
            <div class="flex gap-3">
                <div class="flex-1">
                    <label class="block text-sm font-medium text-gray-700 mb-1">開始日（◯月から）</label>
                    <input type="date" name="effective_from" value="{{ old('effective_from') }}"
                           class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400">
                </div>
                <div class="flex-1">
                    <label class="block text-sm font-medium text-gray-700 mb-1">終了日（◯月まで）</label>
                    <input type="date" name="effective_until" value="{{ old('effective_until') }}"
                           class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400">
                </div>
            </div>
        </div>

        {{-- onetime --}}
        <div id="onetime-fields" class="space-y-4" style="display:none">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">実施日</label>
                <input type="date" name="specific_date" value="{{ old('specific_date') }}"
                       class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400">
            </div>
        </div>

        <div class="flex gap-3">
            <div class="flex-1">
                <label class="block text-sm font-medium text-gray-700 mb-1">開始時刻</label>
                <input type="time" name="start_time" value="{{ old('start_time', '09:00') }}" required
                       class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400">
            </div>
            <div class="flex-1">
                <label class="block text-sm font-medium text-gray-700 mb-1">終了時刻</label>
                <input type="time" name="end_time" value="{{ old('end_time', '10:30') }}" required
                       class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400">
            </div>
        </div>

        <div class="flex gap-3 pt-2">
            <button type="submit"
                    class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-5 py-2 rounded-lg">登録</button>
            <a href="{{ route('schedules.index') }}"
               class="bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm font-medium px-5 py-2 rounded-lg">キャンセル</a>
        </div>
    </form>
</div>

<script>
function toggleType() {
    const type = document.querySelector('input[name="type"]:checked').value;
    document.getElementById('weekly-fields').style.display  = (type === 'weekly')  ? 'block' : 'none';
    document.getElementById('onetime-fields').style.display = (type === 'onetime') ? 'block' : 'none';
}
toggleType();
</script>
@endsection
