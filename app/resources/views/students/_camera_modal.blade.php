{{-- カメラ撮影モーダル --}}
<div id="camera-modal" class="fixed inset-0 z-50 hidden" style="background:#000">

    {{-- ライブプレビュー --}}
    <div id="cam-live" style="display:flex;flex-direction:column;height:100%">
        {{-- 映像エリア（残り全部） --}}
        <div style="position:relative;flex:1;overflow:hidden;background:#000">
            <video id="cam-video" autoplay playsinline
                   style="width:100%;height:100%;object-fit:cover;display:block"></video>
            {{-- ガイド枠 --}}
            <div style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;pointer-events:none">
                <div style="width:260px;height:340px;border:3px dashed rgba(255,255,255,0.7);border-radius:50%"></div>
            </div>
            <p style="position:absolute;top:16px;left:0;right:0;text-align:center;color:#fff;font-size:13px;text-shadow:0 1px 4px rgba(0,0,0,0.8)">
                顔を枠に合わせてください
            </p>
        </div>
        {{-- ボタンエリア（固定高さ） --}}
        <div style="flex:none;background:#111;padding:20px 24px;display:flex;align-items:center;justify-content:center;gap:40px">
            <button type="button" onclick="camClose()"
                    style="color:#aaa;font-size:14px;background:none;border:none;cursor:pointer;padding:8px">
                キャンセル
            </button>
            <button type="button" onclick="camCapture()"
                    style="width:68px;height:68px;border-radius:50%;background:#fff;border:5px solid #555;cursor:pointer;display:flex;align-items:center;justify-content:center;flex-shrink:0">
                <div style="width:52px;height:52px;border-radius:50%;background:#4f46e5"></div>
            </button>
            <div style="width:72px"></div>
        </div>
    </div>

    {{-- 撮影後プレビュー --}}
    <div id="cam-preview" style="display:none;flex-direction:column;height:100%">
        {{-- 画像エリア --}}
        <div style="flex:1;overflow:hidden;background:#000;display:flex;align-items:center;justify-content:center">
            <canvas id="cam-canvas" style="max-width:100%;max-height:100%;object-fit:contain;display:block"></canvas>
        </div>
        {{-- ボタンエリア --}}
        <div style="flex:none;background:#111;padding:16px 24px">
            <input type="text" id="cam-label" placeholder="メモ（正面・左向きなど）"
                   style="width:100%;box-sizing:border-box;padding:10px 14px;border-radius:10px;border:none;font-size:14px;margin-bottom:12px;background:#333;color:#fff">
            <div style="display:flex;gap:12px;justify-content:center">
                <button type="button" onclick="camRetake()"
                        style="padding:11px 24px;border-radius:999px;background:#333;color:#fff;font-size:14px;border:none;cursor:pointer">
                    撮り直す
                </button>
                <button type="button" onclick="camUse()"
                        style="padding:11px 32px;border-radius:999px;background:#4f46e5;color:#fff;font-size:14px;font-weight:600;border:none;cursor:pointer">
                    この写真を使う
                </button>
            </div>
        </div>
    </div>
</div>

<script>
let _camStream = null;

function camOpen() {
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

function camCapture() {
    const video  = document.getElementById('cam-video');
    const canvas = document.getElementById('cam-canvas');
    canvas.width  = video.videoWidth;
    canvas.height = video.videoHeight;
    canvas.getContext('2d').drawImage(video, 0, 0);
    document.getElementById('cam-live').style.display = 'none';
    document.getElementById('cam-preview').style.display = 'flex';
}

function camRetake() {
    document.getElementById('cam-live').style.display = 'flex';
    document.getElementById('cam-preview').style.display = 'none';
}

function camUse() {
    const canvas = document.getElementById('cam-canvas');
    const label  = document.getElementById('cam-label').value;
    canvas.toBlob(blob => {
        const file = new File([blob], 'cam_' + Date.now() + '.jpg', { type: 'image/jpeg' });
        _addCameraRow(file, label);
        camClose();
    }, 'image/jpeg', 0.92);
}

function _addCameraRow(file, label) {
    const container = document.getElementById('face-inputs');

    // hidden file input（フォーム送信用）
    const fileInput = document.createElement('input');
    fileInput.type = 'file';
    fileInput.name = 'face_images[]';
    fileInput.accept = 'image/*';
    fileInput.className = 'hidden';
    const dt = new DataTransfer();
    dt.items.add(file);
    fileInput.files = dt.files;

    // サムネイル
    const thumb = document.createElement('div');
    thumb.style.cssText = 'width:56px;height:56px;border-radius:8px;overflow:hidden;flex-shrink:0;border:2px solid #a5b4fc;';
    const img = document.createElement('img');
    img.src = URL.createObjectURL(file);
    img.style.cssText = 'width:56px;height:56px;object-fit:cover;display:block;';
    thumb.appendChild(img);

    // メモ
    const labelInput = document.createElement('input');
    labelInput.type = 'text';
    labelInput.name = 'face_labels[]';
    labelInput.value = label || '';
    labelInput.placeholder = 'メモ';
    labelInput.className = 'w-24 flex-shrink-0 border rounded-lg px-2 py-2 text-xs text-gray-500';

    // 削除ボタン
    const removeBtn = document.createElement('button');
    removeBtn.type = 'button';
    removeBtn.textContent = '×';
    removeBtn.className = 'flex-shrink-0 w-7 h-7 flex items-center justify-center rounded-full text-gray-400 hover:text-red-500 hover:bg-red-50 text-sm leading-none';

    const row = document.createElement('div');
    row.className = 'face-row flex gap-2 items-center';
    removeBtn.onclick = () => row.remove();

    row.appendChild(fileInput);
    row.appendChild(thumb);
    row.appendChild(labelInput);
    row.appendChild(removeBtn);
    container.appendChild(row);
}

// キーボードショートカット
document.addEventListener('keydown', e => {
    if (document.getElementById('camera-modal').style.display === 'none'
        || document.getElementById('camera-modal').style.display === '') return;

    const inLive    = document.getElementById('cam-live').style.display !== 'none';
    const inPreview = document.getElementById('cam-preview').style.display !== 'none';

    if (e.key === 'Escape')                          { camClose();   e.preventDefault(); }
    else if ((e.key === 'Enter' || e.key === ' ') && inLive)    { camCapture(); e.preventDefault(); }
    else if ((e.key === 'Enter' || e.key === ' ') && inPreview) { camUse();     e.preventDefault(); }
    else if (e.key === 'Backspace' && inPreview)     { camRetake();  e.preventDefault(); }
});
</script>
