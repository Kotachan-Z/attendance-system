@extends('layouts.app')
@section('title', '学生登録')

@section('content')
<div class="max-w-lg mx-auto">
    <h1 class="text-2xl font-bold text-gray-800 mb-6">学生登録</h1>

    {{-- エラーバナー（JS が埋める） --}}
    <div id="error-banner" class="hidden bg-red-50 border border-red-300 text-red-700 rounded-lg px-4 py-3 text-sm mb-4"></div>

    <form id="create-form" enctype="multipart/form-data"
          class="bg-white rounded-xl shadow p-6 space-y-5">
        @csrf

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">氏名</label>
            <input type="text" name="name" value="{{ old('name') }}" required
                   class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400"
                   id="field-name">
            <p id="err-name" class="text-red-500 text-xs mt-1 hidden"></p>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">学籍番号</label>
            <input type="text" name="student_number" value="{{ old('student_number') }}" required
                   class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400"
                   id="field-student-number">
            <p id="err-student-number" class="text-red-500 text-xs mt-1 hidden"></p>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">
                組（クラス）
                <span class="text-gray-400 font-normal text-xs ml-1">例: 1年A組（任意）</span>
            </label>
            <input type="text" name="class_name" value="{{ old('class_name') }}" list="class-name-list"
                   placeholder="1年A組"
                   class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400"
                   id="field-class-name">
            <datalist id="class-name-list">
                @foreach (\App\Models\Student::query()->whereNotNull('class_name')->where('class_name', '!=', '')->distinct()->orderBy('class_name')->pluck('class_name') as $cn)
                    <option value="{{ $cn }}"></option>
                @endforeach
            </datalist>
            <p id="err-class-name" class="text-red-500 text-xs mt-1 hidden"></p>
        </div>

        @if (Auth::user()->isAdmin())
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">
                ログインパスワード
                <span class="text-gray-400 font-normal text-xs ml-1">任意・6文字以上（未入力なら学籍番号と同じ値で発行）</span>
            </label>
            <input type="text" name="login_password" value="{{ old('login_password') }}"
                   placeholder="空欄なら初期パスワード = 学籍番号"
                   class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400"
                   id="field-login-password">
            <p id="err-login-password" class="text-red-500 text-xs mt-1 hidden"></p>
        </div>
        @endif

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">
                顔写真
                <span class="text-gray-400 font-normal text-xs ml-1">複数枚登録で精度向上（最大10枚）</span>
            </label>
            <div id="face-inputs" class="space-y-2">
                <div class="flex gap-2 items-center face-row">
                    <input type="file" name="face_images[]" accept="image/*"
                           class="flex-1 border rounded-lg px-3 py-2 text-sm"
                           onchange="addPreview(this)">
                    <input type="text" name="face_labels[]" placeholder="メモ（正面など）"
                           class="w-32 border rounded-lg px-2 py-2 text-xs text-gray-500">
                </div>
            </div>
            <p id="err-face" class="text-red-500 text-xs mt-1 hidden"></p>

            <div class="mt-2 flex flex-wrap gap-3">
                <button type="button" onclick="addFaceInput()"
                        class="text-xs text-indigo-600 hover:underline">+ ファイルを追加</button>
                <button type="button" onclick="camOpen()"
                        class="text-xs text-indigo-600 hover:underline">📷 1枚撮影</button>
                <button type="button" onclick="camGuideOpen()"
                        class="text-xs font-medium text-indigo-700 hover:underline">📷 ガイド撮影（5枚・精度向上）</button>
            </div>
        </div>

        <div class="flex gap-3 pt-2">
            <button type="submit" id="submit-btn"
                    class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-5 py-2 rounded-lg">
                登録
            </button>
            <a href="{{ route('students.index') }}"
               class="bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm font-medium px-5 py-2 rounded-lg">
                キャンセル
            </a>
        </div>
    </form>
</div>

@include('students._camera_modal')

<script>
// ── フォーム送信（fetch で非同期送信、エラー時はページ維持）──────────────
document.getElementById('create-form').addEventListener('submit', async function(e) {
    e.preventDefault();
    clearErrors();

    // 写真が1枚もない場合
    const inputs = this.querySelectorAll('input[name="face_images[]"]');
    const hasFile = Array.from(inputs).some(i => i.files && i.files.length > 0);
    if (!hasFile) {
        showFieldError('err-face', '顔写真を1枚以上追加してください（ファイルまたはカメラ）');
        return;
    }

    const btn = document.getElementById('submit-btn');
    btn.disabled = true;
    btn.textContent = '登録中…';

    try {
        const resp = await fetch('{{ route('students.store') }}', {
            method: 'POST',
            body: new FormData(this),
            headers: { 'Accept': 'application/json' },
        });

        if (resp.ok) {
            // 成功 → 学生一覧へ
            window.location.href = resp.url || '{{ route('students.index') }}';
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
        btn.textContent = '登録';
    }
});

function clearErrors() {
    document.querySelectorAll('[id^="err-"]').forEach(el => {
        el.textContent = '';
        el.classList.add('hidden');
    });
    document.querySelectorAll('.border-red-400').forEach(el => el.classList.remove('border-red-400'));
    document.getElementById('error-banner').classList.add('hidden');
}

function showFieldError(id, msg) {
    const el = document.getElementById(id);
    if (!el) return;
    el.textContent = msg;
    el.classList.remove('hidden');
}

function showBanner(msg) {
    const el = document.getElementById('error-banner');
    el.textContent = msg;
    el.classList.remove('hidden');
}

function applyErrors(errors) {
    const map = {
        'name':           ['err-name',           'field-name'],
        'student_number': ['err-student-number',  'field-student-number'],
        'login_password': ['err-login-password',  'field-login-password'],
        'face_images':    ['err-face',            null],
    };
    let hasUnmapped = false;
    for (const [key, msgs] of Object.entries(errors)) {
        const base = key.replace(/\.\d+$/, '');   // face_images.0 → face_images
        if (map[base]) {
            showFieldError(map[base][0], msgs[0]);
            if (map[base][1]) {
                document.getElementById(map[base][1])?.classList.add('border-red-400');
            }
        } else {
            hasUnmapped = true;
        }
    }
    if (hasUnmapped) showBanner('入力内容を確認してください。');
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

// ── 写真入力 ──────────────────────────────────────────────────────────────
async function addPreview(input) {
    if (!input.files[0]) return;
    const file = input.files[0];
    const row  = input.closest('.face-row');

    // 既存のサムネイル・バッジをクリア
    row.querySelectorAll('.face-thumb, .quality-badge').forEach(el => el.remove());

    // インラインサムネイル（input の前に挿入）
    const thumb = document.createElement('img');
    thumb.className = 'face-thumb w-14 h-14 object-cover rounded-lg border-2 border-indigo-200 flex-shrink-0';
    thumb.src = URL.createObjectURL(file);
    input.insertAdjacentElement('beforebegin', thumb);

    // 品質バッジ（label input の後ろに挿入）
    const badge = document.createElement('div');
    badge.className = 'quality-badge flex-shrink-0';
    badge.innerHTML = '<span style="font-size:11px;color:#9ca3af">確認中…</span>';
    const labelEl = row.querySelector('input[name="face_labels[]"]');
    if (labelEl) labelEl.insertAdjacentElement('afterend', badge);
    else row.appendChild(badge);

    const q = await analyzeFileQuality(file);
    badge.innerHTML = _buildQualityBadge(q);
}

function addFaceInput() {
    const container = document.getElementById('face-inputs');
    if (container.children.length >= 10) return;
    const row = document.createElement('div');
    row.className = 'face-row flex gap-2 items-center';
    row.innerHTML = `
        <input type="file" name="face_images[]" accept="image/*"
               class="flex-1 min-w-0 border rounded-lg px-3 py-2 text-sm" onchange="addPreview(this)">
        <input type="text" name="face_labels[]" placeholder="メモ（左向きなど）"
               class="w-28 flex-shrink-0 border rounded-lg px-2 py-2 text-xs text-gray-500">
        <button type="button" onclick="this.closest('.face-row').remove()"
                class="flex-shrink-0 w-7 h-7 flex items-center justify-center rounded-full text-gray-400 hover:text-red-500 hover:bg-red-50 text-sm leading-none">×</button>
    `;
    container.appendChild(row);
}
</script>
@endsection
