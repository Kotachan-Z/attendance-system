@extends('layouts.app')
@section('title', $course->name . ' — 履修登録')

@section('content')
<div class="max-w-2xl mx-auto">
    <h1 class="text-2xl font-bold text-gray-800 mb-1">{{ $course->name }}</h1>
    <p class="text-sm text-gray-500 mb-6">この授業を履修する学生を選択してください。チェックした学生が出席対象になり、未検出なら欠席として記録されます。</p>

    <form method="POST" action="{{ route('courses.update', $course) }}"
          class="bg-white rounded-xl shadow p-6 space-y-5">
        @csrf @method('PUT')

        {{-- 担当教員（複数可） --}}
        <div>
            <span class="text-sm font-medium text-gray-700">担当教員（複数選択可）</span>
            @if ($teachers->isEmpty())
                <p class="text-gray-500 text-sm mt-2">教職員アカウントがありません。</p>
            @else
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 mt-2 border rounded-lg p-3">
                @foreach ($teachers as $teacher)
                <label class="flex items-center gap-2 text-sm px-2 py-1 rounded hover:bg-gray-50 cursor-pointer">
                    <input type="checkbox" name="teacher_ids[]" value="{{ $teacher->id }}"
                           class="rounded text-indigo-600"
                           @checked(in_array($teacher->id, $teacherIds))>
                    <span class="text-gray-800">{{ $teacher->name }}</span>
                    <span class="text-xs px-1.5 py-0.5 rounded {{ $teacher->isAdmin() ? 'bg-purple-100 text-purple-700' : 'bg-gray-100 text-gray-500' }}">
                        {{ $teacher->isAdmin() ? '管理者' : '教員' }}
                    </span>
                </label>
                @endforeach
            </div>
            @endif
        </div>

        <hr class="border-gray-100">

        <div class="flex items-center justify-between">
            <span class="text-sm font-medium text-gray-700">履修学生（在籍 {{ count($students) }}名中）</span>
            <div class="text-xs">
                <button type="button" onclick="toggleAll(true)" class="text-indigo-600 hover:underline mr-3">すべて選択</button>
                <button type="button" onclick="toggleAll(false)" class="text-gray-500 hover:underline">すべて解除</button>
            </div>
        </div>

        @if ($students->isEmpty())
            <p class="text-gray-500 text-sm">学生が登録されていません。先に学生を登録してください。</p>
        @else
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 max-h-96 overflow-y-auto border rounded-lg p-3">
            @foreach ($students as $student)
            <label class="flex items-center gap-2 text-sm px-2 py-1 rounded hover:bg-gray-50 cursor-pointer">
                <input type="checkbox" name="student_ids[]" value="{{ $student->id }}"
                       class="enroll-cb rounded text-indigo-600"
                       @checked(in_array($student->id, $enrolledIds))>
                <span class="text-gray-800">{{ $student->name }}</span>
                <span class="text-gray-400 text-xs">{{ $student->student_number }}</span>
            </label>
            @endforeach
        </div>
        @endif

        <div class="flex gap-3 pt-2">
            <button type="submit"
                    class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-5 py-2 rounded-lg">保存</button>
            <a href="{{ route('courses.index') }}"
               class="bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm font-medium px-5 py-2 rounded-lg">戻る</a>
        </div>
    </form>
</div>

<script>
function toggleAll(state) {
    document.querySelectorAll('.enroll-cb').forEach(cb => cb.checked = state);
}
</script>
@endsection
