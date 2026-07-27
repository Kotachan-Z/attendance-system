{{-- セッションの状態バッジ（予定 / 進行中 / 終了）。$session を渡す --}}
@php $st = $session->liveStatus(); @endphp
@if ($st === 'active')
    <span class="text-xs bg-emerald-50 text-emerald-600 font-semibold px-2 py-0.5 rounded">進行中</span>
@elseif ($st === 'upcoming')
    <span class="text-xs bg-sky-50 text-sky-600 font-semibold px-2 py-0.5 rounded">予定</span>
@else
    <span class="text-xs bg-gray-100 text-gray-500 font-semibold px-2 py-0.5 rounded">終了</span>
@endif
