{{--
    組（クラス）プルダウン + 「新しい組を追加」ボタン（共通部品）

    パラメータ:
      $selected  … 現在選択中の組名（編集時のプリセレクト。無ければ null）
      $name      … select の name 属性（既定 'class_name'）
      $canAdd    … 追加ボタンを表示するか（管理者のみ true。既定 Auth::user()->isAdmin()）

    使い方:
      @include('students._class_select', ['selected' => $student->class_name])
--}}
@php
    $name     = $name     ?? 'class_name';
    $canAdd   = $canAdd   ?? (Auth::check() && Auth::user()->isAdmin());
    $selected = $selected ?? null;
    $options  = \App\Models\ClassGroup::options();
    // 選択中の値がマスタに無い場合も選べるように補う（古い割り当ての保護）
    if ($selected && ! in_array($selected, $options, true)) {
        $options[] = $selected;
    }
@endphp

<div class="flex items-center gap-2">
    <select name="{{ $name }}"
            class="class-select flex-1 min-w-0 border rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-indigo-300">
        <option value="">（未設定）</option>
        @foreach ($options as $opt)
            <option value="{{ $opt }}" @selected($selected === $opt)>{{ $opt }}</option>
        @endforeach
    </select>

    @if ($canAdd)
    <button type="button" onclick="openAddClassModal()"
            class="flex-shrink-0 text-sm text-indigo-600 hover:text-indigo-700 hover:bg-indigo-50 border border-indigo-200 rounded-lg px-3 py-2 whitespace-nowrap">
        ＋ 新しい組
    </button>
    @endif
</div>

@once
@if ($canAdd)
{{-- 組追加モーダル（ページに1つだけ出力）--}}
<div id="add-class-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40 px-4"
     style="display:none" onclick="if(event.target===this) closeAddClassModal()">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-sm p-6">
        <h3 class="text-base font-semibold text-gray-800 mb-1">新しい組を追加</h3>
        <p class="text-xs text-gray-400 mb-4">英数字で入力してください（例: IE3A / SK2A）。追加すると全ての組プルダウンに反映されます。</p>

        <input type="text" id="add-class-input" placeholder="例: IE3A" maxlength="50"
               class="w-full border rounded-lg px-3 py-2 text-sm uppercase focus:outline-none focus:ring-2 focus:ring-indigo-300"
               onkeydown="if(event.key==='Enter'){event.preventDefault();submitAddClass();}">
        <p id="add-class-error" class="text-rose-500 text-xs mt-1 hidden"></p>

        <div class="flex justify-end gap-2 mt-5">
            <button type="button" onclick="closeAddClassModal()"
                    class="text-sm text-gray-500 hover:bg-gray-100 px-4 py-2 rounded-lg">キャンセル</button>
            <button type="button" id="add-class-submit" onclick="submitAddClass()"
                    class="bg-indigo-500 hover:bg-indigo-600 text-white text-sm font-medium px-5 py-2 rounded-lg">追加</button>
        </div>
    </div>
</div>

<script>
function openAddClassModal() {
    const m = document.getElementById('add-class-modal');
    m.style.display = 'flex';
    document.getElementById('add-class-error').classList.add('hidden');
    const inp = document.getElementById('add-class-input');
    inp.value = '';
    setTimeout(() => inp.focus(), 30);
}
function closeAddClassModal() {
    document.getElementById('add-class-modal').style.display = 'none';
}
async function submitAddClass() {
    const inp   = document.getElementById('add-class-input');
    const err   = document.getElementById('add-class-error');
    const btn   = document.getElementById('add-class-submit');
    const name  = inp.value.trim().toUpperCase();

    err.classList.add('hidden');
    if (!name) { err.textContent = '組名を入力してください。'; err.classList.remove('hidden'); return; }
    if (!/^[A-Za-z0-9]+$/.test(name)) {
        err.textContent = '英数字で入力してください（例: IE3A）。'; err.classList.remove('hidden'); return;
    }

    btn.disabled = true; btn.textContent = '追加中…';
    try {
        const resp = await fetch('{{ route('class-groups.store') }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
            },
            body: JSON.stringify({ name }),
        });

        if (resp.ok) {
            // 全ての組プルダウンに新しい選択肢を追加し、選択状態にする
            document.querySelectorAll('select.class-select').forEach(sel => {
                if (![...sel.options].some(o => o.value === name)) {
                    sel.appendChild(new Option(name, name));
                }
                sel.value = name;
            });
            closeAddClassModal();
        } else {
            const data = await resp.json().catch(() => ({}));
            let msg = data.message || '追加に失敗しました。';
            if (data.errors && data.errors.name) msg = data.errors.name[0];
            err.textContent = msg; err.classList.remove('hidden');
        }
    } catch (e) {
        err.textContent = '通信エラー: ' + e.message; err.classList.remove('hidden');
    } finally {
        btn.disabled = false; btn.textContent = '追加';
    }
}
</script>
@endif
@endonce
