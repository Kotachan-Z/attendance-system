{{-- カメラ撮影モーダル（シングル + ガイド5枚モード）--}}
<div id="camera-modal" class="fixed inset-0 z-50 hidden" style="background:#000">

    {{-- ライブプレビュー（シングル・ガイド共用） --}}
    <div id="cam-live" style="display:flex;flex-direction:column;height:100%">

        {{-- 映像エリア --}}
        <div style="position:relative;flex:1;overflow:hidden;background:#000">
            <video id="cam-video" autoplay playsinline
                   style="width:100%;height:100%;object-fit:cover;display:block"></video>

            {{-- 楕円ガイド枠 --}}
            <div style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;pointer-events:none">
                <div style="width:260px;height:340px;border:3px dashed rgba(255,255,255,0.7);border-radius:50%"></div>
            </div>

            {{-- ガイドモード用オーバーレイ --}}
            <div id="guide-overlay" style="display:none;position:absolute;top:0;left:0;right:0;pointer-events:none">
                <div id="guide-dots" style="display:flex;justify-content:center;gap:8px;padding:16px 0 6px"></div>
                <div style="text-align:center">
                    <span id="guide-label" style="color:#fff;font-size:19px;font-weight:700;text-shadow:0 1px 6px rgba(0,0,0,.9)"></span>
                </div>
                <p id="guide-hint" style="text-align:center;color:#ddd;font-size:13px;text-shadow:0 1px 4px rgba(0,0,0,.8);margin:4px 0 0"></p>
            </div>

            {{-- シングルモード用ヒント --}}
            <p id="cam-single-hint" style="position:absolute;top:16px;left:0;right:0;text-align:center;color:#fff;font-size:13px;text-shadow:0 1px 4px rgba(0,0,0,.8)">
                顔を枠に合わせてください
            </p>
        </div>

        {{-- ボタンエリア --}}
        <div style="flex:none;background:#111;padding:20px 24px;display:flex;align-items:center;justify-content:space-between">
            <button type="button" onclick="camClose()"
                    style="color:#aaa;font-size:14px;background:none;border:none;cursor:pointer;padding:8px;min-width:80px;text-align:left">
                キャンセル
            </button>
            <button type="button" onclick="camCapture()"
                    style="width:68px;height:68px;border-radius:50%;background:#fff;border:5px solid #555;cursor:pointer;display:flex;align-items:center;justify-content:center;flex-shrink:0">
                <div style="width:52px;height:52px;border-radius:50%;background:#4f46e5"></div>
            </button>
            <div style="min-width:80px;text-align:right">
                <span id="guide-counter" style="display:none;color:#888;font-size:13px"></span>
            </div>
        </div>
    </div>

    {{-- 撮影後プレビュー --}}
    <div id="cam-preview" style="display:none;flex-direction:column;height:100%">

        {{-- 画像 + 品質バッジ --}}
        <div style="flex:1;overflow:hidden;background:#111;display:flex;flex-direction:column;align-items:center;justify-content:center;padding:16px">
            <canvas id="cam-canvas" style="max-width:100%;max-height:calc(100% - 48px);object-fit:contain;border-radius:12px"></canvas>
            <div id="quality-badge" style="margin-top:14px;min-height:24px;text-align:center"></div>
        </div>

        {{-- 操作エリア --}}
        <div style="flex:none;background:#111;padding:16px 24px;border-top:1px solid #222">
            <input type="text" id="cam-label" placeholder="メモ（正面・左向きなど）"
                   style="width:100%;box-sizing:border-box;padding:10px 14px;border-radius:10px;border:none;font-size:14px;margin-bottom:12px;background:#333;color:#fff">
            <div style="display:flex;gap:12px;justify-content:center">
                <button type="button" onclick="camRetake()"
                        style="padding:11px 24px;border-radius:999px;background:#333;color:#fff;font-size:14px;border:none;cursor:pointer">
                    撮り直す
                </button>
                {{-- シングルモード --}}
                <button type="button" id="btn-use-single" onclick="camUse()"
                        style="padding:11px 32px;border-radius:999px;background:#4f46e5;color:#fff;font-size:14px;font-weight:600;border:none;cursor:pointer">
                    この写真を使う
                </button>
                {{-- ガイドモード --}}
                <button type="button" id="btn-use-guide" onclick="camGuideNext()"
                        style="display:none;padding:11px 32px;border-radius:999px;background:#4f46e5;color:#fff;font-size:14px;font-weight:600;border:none;cursor:pointer">
                    次の角度へ →
                </button>
            </div>
        </div>
    </div>
</div>

<script>
// ── 状態変数 ────────────────────────────────────────────────────────────────
let _camStream  = null;
let _guideMode  = false;
let _guideStep  = 0;
let _guideSaved = [];   // { file, label }[]

const GUIDE_STEPS = [
    { label: '① 正面',    hint: 'カメラを正面から見てください' },
    { label: '② 右斜め',  hint: '顔を少し右に向けてください（約30°）' },
    { label: '③ 左斜め',  hint: '顔を少し左に向けてください（約30°）' },
    { label: '④ やや上',  hint: '少し上を向いてください' },
    { label: '⑤ やや下',  hint: '少し下を向いてください' },
];

// ── 起動 ────────────────────────────────────────────────────────────────────
function camOpen() {
    _guideMode = false;
    _openModal();
    document.getElementById('cam-single-hint').style.display = '';
    document.getElementById('guide-overlay').style.display = 'none';
    document.getElementById('guide-counter').style.display = 'none';
}

function camGuideOpen() {
    _guideMode  = true;
    _guideStep  = 0;
    _guideSaved = [];
    _openModal();
    document.getElementById('cam-single-hint').style.display = 'none';
    _renderGuideStep();
}

function _openModal() {
    document.getElementById('camera-modal').style.display = 'block';
    document.getElementById('cam-live').style.display = 'flex';
    document.getElementById('cam-preview').style.display = 'none';
    document.getElementById('cam-label').value = '';

    navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user', width: 1280, height: 960 } })
        .then(stream => {
            _camStream = stream;
            document.getElementById('cam-video').srcObject = stream;
        })
        .catch(err => {
            alert('カメラにアクセスできませんでした。\n' + err.message);
            camClose();
        });
}

function camClose() {
    if (_camStream) { _camStream.getTracks().forEach(t => t.stop()); _camStream = null; }
    document.getElementById('camera-modal').style.display = 'none';
}

// ── ガイドステップ表示 ──────────────────────────────────────────────────────
function _renderGuideStep() {
    const step = GUIDE_STEPS[_guideStep];
    document.getElementById('guide-overlay').style.display = '';
    document.getElementById('guide-label').textContent = step.label;
    document.getElementById('guide-hint').textContent   = step.hint;
    document.getElementById('guide-counter').style.display = '';
    document.getElementById('guide-counter').textContent = `${_guideStep + 1} / ${GUIDE_STEPS.length}`;

    // ドットインジケーター
    const dots = document.getElementById('guide-dots');
    dots.innerHTML = '';
    GUIDE_STEPS.forEach((_, i) => {
        const dot = document.createElement('div');
        dot.style.cssText = 'width:8px;height:8px;border-radius:50%;' +
            (i < _guideStep ? 'background:#6ee7b7;' :
             i === _guideStep ? 'background:#fff;box-shadow:0 0 0 2px rgba(255,255,255,.4);' :
             'background:#444;');
        dots.appendChild(dot);
    });
}

// ── 撮影 ────────────────────────────────────────────────────────────────────
function camCapture() {
    const video  = document.getElementById('cam-video');
    const canvas = document.getElementById('cam-canvas');
    canvas.width  = video.videoWidth;
    canvas.height = video.videoHeight;
    canvas.getContext('2d').drawImage(video, 0, 0);

    // ガイドモードは角度名を自動入力
    if (_guideMode) {
        const label = GUIDE_STEPS[_guideStep].label.replace(/^[①-⑤]\s*/, '');
        document.getElementById('cam-label').value = label;
    }

    // ライブ → プレビューへ切り替え
    document.getElementById('cam-live').style.display    = 'none';
    document.getElementById('cam-preview').style.display = 'flex';

    // ボタン切り替え
    const isFinal = _guideMode && _guideStep >= GUIDE_STEPS.length - 1;
    document.getElementById('btn-use-single').style.display = _guideMode ? 'none' : '';
    document.getElementById('btn-use-guide').style.display  = _guideMode ? '' : 'none';
    document.getElementById('btn-use-guide').textContent =
        isFinal ? '✓ 完了・全て追加する' : '次の角度へ →';

    // 品質チェック（非同期）
    const badge = document.getElementById('quality-badge');
    badge.innerHTML = '<span style="font-size:12px;color:#666">品質を確認中…</span>';
    _analyzeCanvasQuality(canvas).then(q => { badge.innerHTML = _buildQualityBadge(q); });
}

function camRetake() {
    document.getElementById('cam-live').style.display    = 'flex';
    document.getElementById('cam-preview').style.display = 'none';
    if (_guideMode) _renderGuideStep();
}

// ── シングルモード: 追加 ─────────────────────────────────────────────────────
function camUse() {
    const canvas = document.getElementById('cam-canvas');
    const label  = document.getElementById('cam-label').value;
    canvas.toBlob(blob => {
        _addFaceRow(new File([blob], 'cam_' + Date.now() + '.jpg', { type: 'image/jpeg' }), label);
        camClose();
    }, 'image/jpeg', 0.92);
}

// ── ガイドモード: 次ステップ / 完了 ─────────────────────────────────────────
function camGuideNext() {
    const canvas = document.getElementById('cam-canvas');
    const label  = document.getElementById('cam-label').value ||
                   GUIDE_STEPS[_guideStep].label.replace(/^[①-⑤]\s*/, '');

    canvas.toBlob(blob => {
        _guideSaved.push({
            file:  new File([blob], `guide_${_guideStep}_` + Date.now() + '.jpg', { type: 'image/jpeg' }),
            label,
        });

        if (_guideStep >= GUIDE_STEPS.length - 1) {
            // 最終ステップ → 全て追加して閉じる
            _guideSaved.forEach(s => _addFaceRow(s.file, s.label));
            camClose();
        } else {
            // 次のステップへ
            _guideStep++;
            _renderGuideStep();
            document.getElementById('cam-preview').style.display = 'none';
            document.getElementById('cam-live').style.display    = 'flex';
        }
    }, 'image/jpeg', 0.92);
}

// ── フォームに写真行を追加（create / edit 両ページで使う共通関数）──────────
function _addFaceRow(file, label) {
    const container = document.getElementById('face-inputs');
    if (!container) return;

    // hidden file input（フォーム送信用）
    const fileInput = document.createElement('input');
    fileInput.type = 'file';
    fileInput.name = 'face_images[]';
    fileInput.className = 'hidden';
    const dt = new DataTransfer();
    dt.items.add(file);
    fileInput.files = dt.files;

    // サムネイル
    const thumb = document.createElement('div');
    thumb.style.cssText = 'width:56px;height:56px;border-radius:8px;overflow:hidden;flex-shrink:0;border:2px solid #a5b4fc;';
    const img = document.createElement('img');
    img.src = URL.createObjectURL(file);
    img.style.cssText = 'width:100%;height:100%;object-fit:cover;display:block;';
    thumb.appendChild(img);

    // ラベル
    const labelInput = document.createElement('input');
    labelInput.type = 'text';
    labelInput.name = 'face_labels[]';
    labelInput.value = label || '';
    labelInput.placeholder = 'メモ';
    labelInput.style.cssText = 'flex:1;min-width:0;border:1px solid #d1d5db;border-radius:8px;padding:5px 8px;font-size:12px;color:#374151;';

    // 品質バッジ
    const badge = document.createElement('div');
    badge.style.cssText = 'flex-shrink:0;';
    badge.innerHTML = '<span style="font-size:11px;color:#9ca3af">確認中…</span>';
    analyzeFileQuality(file).then(q => { badge.innerHTML = _buildQualityBadge(q); });

    // 削除ボタン
    const removeBtn = document.createElement('button');
    removeBtn.type = 'button';
    removeBtn.textContent = '×';
    removeBtn.style.cssText = 'flex-shrink:0;width:26px;height:26px;border-radius:50%;border:none;background:none;color:#9ca3af;font-size:16px;cursor:pointer;line-height:1;';

    const row = document.createElement('div');
    row.className = 'face-row';
    row.style.cssText = 'display:flex;gap:8px;align-items:center;';
    removeBtn.onclick = () => row.remove();

    [fileInput, thumb, labelInput, badge, removeBtn].forEach(el => row.appendChild(el));
    container.appendChild(row);
}

// ── 品質チェック ─────────────────────────────────────────────────────────────
async function _analyzeCanvasQuality(canvas) {
    const SIZE = 180;
    const tmp = document.createElement('canvas');
    tmp.width = tmp.height = SIZE;
    tmp.getContext('2d').drawImage(canvas, 0, 0, SIZE, SIZE);
    const data = tmp.getContext('2d').getImageData(0, 0, SIZE, SIZE).data;

    // 輝度
    let brightness = 0;
    const g = [];
    for (let i = 0; i < data.length; i += 4) {
        const lum = data[i] * 0.299 + data[i+1] * 0.587 + data[i+2] * 0.114;
        brightness += lum;
        g.push(lum);
    }
    brightness /= SIZE * SIZE;

    // Laplacian 分散（ブレ検出）
    let lap = 0, cnt = 0;
    for (let y = 1; y < SIZE - 1; y++) {
        for (let x = 1; x < SIZE - 1; x++) {
            const i = y * SIZE + x;
            const v = 4*g[i] - g[i-1] - g[i+1] - g[i-SIZE] - g[i+SIZE];
            lap += v * v; cnt++;
        }
    }
    return {
        width: canvas.width, height: canvas.height,
        brightness: Math.round(brightness),
        sharpness:  Math.round(lap / cnt),
        brightOk: brightness > 40 && brightness < 220,
        sharpOk:  (lap / cnt) > 100,
        sizeOk:   Math.min(canvas.width, canvas.height) >= 200,
    };
}

async function analyzeFileQuality(file) {
    return new Promise(resolve => {
        const img = new Image();
        const url = URL.createObjectURL(file);
        img.onload = () => {
            const c = document.createElement('canvas');
            c.width = img.width; c.height = img.height;
            c.getContext('2d').drawImage(img, 0, 0);
            URL.revokeObjectURL(url);
            _analyzeCanvasQuality(c).then(resolve);
        };
        img.src = url;
    });
}

function _buildQualityBadge(q) {
    const issues = [];
    if (!q.sizeOk)   issues.push('解像度低');
    if (!q.brightOk) issues.push(q.brightness <= 40 ? '暗すぎ' : '明るすぎ');
    if (!q.sharpOk)  issues.push('ブレあり?');

    if (!issues.length) {
        return '<span style="font-size:11px;background:#d1fae5;color:#065f46;border-radius:999px;padding:2px 9px;font-weight:600">✓ 品質OK</span>';
    }
    return '<span title="撮り直すと精度が上がります" style="font-size:11px;background:#fef3c7;color:#92400e;border-radius:999px;padding:2px 9px;font-weight:600;cursor:help">⚠ ' + issues.join('・') + '</span>';
}

// ── キーボードショートカット ──────────────────────────────────────────────────
document.addEventListener('keydown', e => {
    const modal = document.getElementById('camera-modal');
    if (!modal || modal.style.display === 'none' || modal.style.display === '') return;
    const inLive    = document.getElementById('cam-live').style.display    !== 'none';
    const inPreview = document.getElementById('cam-preview').style.display !== 'none';

    if (e.key === 'Escape')                                  { camClose();   e.preventDefault(); }
    else if ((e.key === ' ' || e.key === 'Enter') && inLive) { camCapture(); e.preventDefault(); }
    else if (e.key === 'Enter' && inPreview)                 { _guideMode ? camGuideNext() : camUse(); e.preventDefault(); }
    else if (e.key === 'Backspace' && inPreview)             { camRetake();  e.preventDefault(); }
});
</script>
