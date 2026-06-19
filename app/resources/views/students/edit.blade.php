@extends('layouts.app')
@section('title', '学生情報編集')

@section('content')
<div class="max-w-lg mx-auto">
    <h1 class="text-2xl font-bold text-gray-800 mb-6">学生情報編集</h1>

    {{-- エラーバナー（JS が埋める） --}}
    <div id="error-banner" class="hidden bg-red-50 border border-red-300 text-red-700 rounded-lg px-4 py-3 text-sm mb-4"></div>

    <form id="edit-form" enctype="multipart/form-data"
          class="bg-white rounded-xl shadow p-6 space-y-5">
        @csrf
        <input type="hidden" name="_method" value="PUT">

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">氏名</label>
            <input type="text" name="name" value="{{ old('name', $student->name) }}" required
                   id="field-name"
                   class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400">
            <p id="err-name" class="text-red-500 text-xs mt-1 hidden"></p>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">学籍番号</label>
            <input type="text" name="student_number" value="{{ old('student_number', $student->student_number) }}" required
                   id="field-student-number"
                   class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400">
            <p id="err-student-number" class="text-red-500 text-xs mt-1 hidden"></p>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">
                組（クラス）
                <span class="text-gray-400 font-normal text-xs ml-1">例: 1年A組（任意）</span>
            </label>
            <input type="text" name="class_name" value="{{ old('class_name', $student->class_name) }}" list="class-name-list"
                   placeholder="1年A組" id="field-class-name"
                   class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400">
            <datalist id="class-name-list">
                @foreach (\App\Models\Student::query()->whereNotNull('class_name')->where('class_name', '!=', '')->distinct()->orderBy('class_name')->pluck('class_name') as $cn)
                    <option value="{{ $cn }}"></option>
                @endforeach
            </datalist>
            <p id="err-class-name" class="text-red-500 text-xs mt-1 hidden"></p>
        </div>

        @if (Auth::user()->isAdmin())
        {{-- 生徒ログイン設定（管理者のみ） --}}
        <div class="border border-gray-200 rounded-lg p-4 space-y-4 bg-gray-50">
            <p class="text-sm font-semibold text-gray-700">生徒ログイン設定</p>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    ログインパスワード
                    <span class="text-gray-400 font-normal text-xs ml-1">
                        @if (filled($student->password))設定済み（変更する場合のみ入力・6文字以上）@else未設定（入力すると有効化・6文字以上）@endif
                    </span>
                </label>
                <input type="text" name="login_password" value="{{ old('login_password') }}"
                       placeholder="変更しない場合は空欄"
                       id="field-login-password"
                       class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400">
                <p id="err-login-password" class="text-red-500 text-xs mt-1 hidden"></p>
            </div>

            <label class="flex items-center gap-2">
                <input type="checkbox" name="withdrawn" value="1" @checked(old('withdrawn', $student->isWithdrawn()))
                       class="rounded border-gray-300 text-red-600 focus:ring-red-500">
                <span class="text-sm text-gray-700">退学扱いにする</span>
                <span class="text-xs text-gray-400">（ログイン不可・名簿および出欠集計から除外。過去の記録は残ります）</span>
            </label>
            @if ($student->isWithdrawn())
                <p class="text-xs text-red-500">退学日: {{ $student->withdrawn_at->format('Y/m/d') }}</p>
            @endif
        </div>
        @endif

        {{-- 登録済み写真 --}}
        @if ($student->faces->isNotEmpty())
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">
                登録済み写真 ({{ $student->faces->count() }}枚)
            </label>
            <div class="flex flex-wrap gap-3">
                @foreach ($student->faces as $face)
                <div class="relative group">
                    <img src="{{ Storage::url($face->image_path) }}"
                         class="w-20 h-20 object-cover rounded-lg border-2 border-indigo-100"
                         title="{{ $face->label ?: '(ラベルなし)' }}">
                    @if ($face->label)
                        <span class="absolute bottom-0 left-0 right-0 text-center text-white text-xs bg-black/50 rounded-b-lg py-0.5">{{ $face->label }}</span>
                    @endif
                    <button type="button"
                            onclick="if(confirm('削除しますか？')) document.getElementById('delete-face-{{ $face->id }}').submit()"
                            class="absolute -top-1 -right-1 hidden group-hover:flex w-5 h-5 bg-red-500 text-white rounded-full text-xs items-center justify-center">×</button>
                </div>
                @endforeach
            </div>
        </div>
        @endif

        {{-- 新規写真追加 --}}
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">
                写真を追加
                <span class="text-gray-400 font-normal text-xs ml-1">（JPEG/PNG、1枚最大20MB）</span>
            </label>

            {{-- バリデーションエラー表示 --}}
            @if ($errors->has('face_images') || $errors->has('face_images.*'))
                <div class="bg-red-50 border border-red-300 text-red-700 rounded px-3 py-2 text-sm mb-2">
                    @foreach ($errors->get('face_images.*') as $msgs)
                        @foreach ($msgs as $msg)<p>{{ $msg }}</p>@endforeach
                    @endforeach
                    @error('face_images') <p>{{ $message }}</p> @enderror
                </div>
            @endif

            <div id="face-inputs" class="space-y-2">
                {{-- デフォルトで1行表示 --}}
                <div class="face-row flex gap-2 items-center">
                    <div class="preview-thumb rounded-lg border border-gray-200 bg-gray-50 overflow-hidden flex-shrink-0" style="width:56px;height:56px;display:none;"></div>
                    <input type="file" name="face_images[]" accept="image/*"
                           class="flex-1 min-w-0 border rounded-lg px-3 py-2 text-sm" onchange="updatePreview(this)">
                    <input type="text" name="face_labels[]" placeholder="メモ"
                           class="w-24 flex-shrink-0 border rounded-lg px-2 py-2 text-xs text-gray-500">
                    <button type="button" onclick="removeRow(this)"
                            class="flex-shrink-0 w-7 h-7 flex items-center justify-center rounded-full text-gray-400 hover:text-red-500 hover:bg-red-50 text-sm leading-none">×</button>
                </div>
            </div>
            <div class="mt-2 flex gap-3">
                <button type="button" onclick="addFaceInput()"
                        class="text-xs text-indigo-600 hover:underline">+ ファイルを追加</button>
                <button type="button" onclick="camOpen()"
                        class="text-xs text-indigo-600 hover:underline">📷 カメラで撮影</button>
            </div>
        </div>

        <div class="flex gap-3 pt-2">
            <button type="submit" id="submit-btn"
                    class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-5 py-2 rounded-lg">更新</button>
            <a href="{{ route('students.show', $student) }}"
               class="bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm font-medium px-5 py-2 rounded-lg">キャンセル</a>
        </div>
    </form>
</div>

@include('students._camera_modal')

{{-- 写真削除フォーム（メインフォームの外に置く必要がある） --}}
@foreach ($student->faces as $face)
<form id="delete-face-{{ $face->id }}" method="POST"
      action="{{ route('students.faces.destroy', [$student, $face]) }}"
      style="display:none">
    @csrf @method('DELETE')
</form>
@endforeach

<script>
// ── フォーム送信（fetch で非同期、エラー時はページ維持）──────────────────
document.getElementById('edit-form').addEventListener('submit', async function(e) {
    e.preventDefault();
    clearErrors();

    const btn = document.getElementById('submit-btn');
    btn.disabled = true;
    btn.textContent = '更新中…';

    try {
        const resp = await fetch('{{ route('students.update', $student) }}', {
            method: 'POST',
            body: new FormData(this),
            headers: { 'Accept': 'application/json' },
        });

        if (resp.ok) {
            window.location.href = resp.url || '{{ route('students.show', $student) }}';
        } else if (resp.status === 422) {
            const { errors } = await resp.json();
            applyErrors(errors);
        } else {
            showBanner('予期しないエラーが発生しました（' + resp.status + '）');
        }
    } catch (err) {
        showBanner('通信エラー: ' + err.message);
    } finally {
        btn.disabled = false;
        btn.textContent = '更新';
    }
});

function clearErrors() {
    document.querySelectorAll('[id^="err-"]').forEach(el => { el.textContent = ''; el.classList.add('hidden'); });
    document.getElementById('error-banner').classList.add('hidden');
}
function showBanner(msg) {
    const el = document.getElementById('error-banner');
    el.textContent = msg;
    el.classList.remove('hidden');
}
function applyErrors(errors) {
    const map = {
        'name':           'err-name',
        'student_number': 'err-student-number',
        'login_password': 'err-login-password',
        'face_images':    'err-face',
    };
    for (const [key, msgs] of Object.entries(errors)) {
        const base = key.replace(/\.\d+$/, '');
        const errId = map[base];
        if (errId) {
            const el = document.getElementById(errId);
            if (el) { el.textContent = msgs[0]; el.classList.remove('hidden'); }
        }
    }
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

// ── 写真入力 ──────────────────────────────────────────────────────────────
function updatePreview(input) {
    if (!input.files[0]) return;
    const row = input.closest('.face-row');
    const thumb = row.querySelector('.preview-thumb');
    const img = document.createElement('img');
    img.src = URL.createObjectURL(input.files[0]);
    img.style.cssText = 'width:56px;height:56px;object-fit:cover;display:block;';
    thumb.innerHTML = '';
    thumb.appendChild(img);
    thumb.style.display = 'block';
}
function removeRow(btn) {
    btn.closest('.face-row').remove();
}
function addFaceInput() {
    const container = document.getElementById('face-inputs');
    const div = document.createElement('div');
    div.className = 'face-row flex gap-2 items-center';
    div.innerHTML = `
        <div class="preview-thumb rounded-lg border border-gray-200 bg-gray-50 overflow-hidden flex-shrink-0" style="width:56px;height:56px;display:none;"></div>
        <input type="file" name="face_images[]" accept="image/*"
               class="flex-1 min-w-0 border rounded-lg px-3 py-2 text-sm" onchange="updatePreview(this)">
        <input type="text" name="face_labels[]" placeholder="メモ"
               class="w-24 flex-shrink-0 border rounded-lg px-2 py-2 text-xs text-gray-500">
        <button type="button" onclick="removeRow(this)"
                class="flex-shrink-0 w-7 h-7 flex items-center justify-center rounded-full text-gray-400 hover:text-red-500 hover:bg-red-50 text-sm leading-none">×</button>
    `;
    container.appendChild(div);
}
</script>
@endsection
