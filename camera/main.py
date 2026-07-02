"""
OAK-D-Lite-FF 出席管理カメラスクリプト（depthai 3.x 対応）

処理フロー:
1. DepthAI パイプライン起動（RGB + 深度 aligned）
2. アクティブセッション取得
3. 学生埋め込みキャッシュ構築
4. ループ: 顔検出 → 深度なりすまし検出 → DeepFace 照合 → API 出席記録
"""
import argparse
import io
import logging
import os
import sys
import time

import cv2
import numpy as np
from PIL import Image

import api_client
import face_detector
from config import (
    CAMERA_FPS,
    CAMERA_HEIGHT,
    CAMERA_WIDTH,
    COOLDOWN_SEC,
    DEBUG_DISPLAY,
    DETECT_INTERVAL,
    DETECTION_LOG_COOLDOWN_SEC,
    JPEG_QUALITY,
    MIN_FACE_SIZE,
    STUDENT_CACHE_TTL_SEC,
    TEMPORAL_VOTE_MIN,
)
from depth_checker import is_live_face
from face_detector import compute_centroid

logging.basicConfig(
    level=logging.INFO,
    format="%(asctime)s [%(levelname)s] %(message)s",
    datefmt="%H:%M:%S",
)
logger = logging.getLogger(__name__)


# 埋め込みキャッシュ: 画像パス → (更新時刻, 埋め込み)。
# キャッシュ更新のたびに全写真を InsightFace で再推論するとメインループが
# 数秒〜数十秒止まる（特に RPi）ため、変化のない写真の結果を再利用する。
# 顔が検出できなかった写真も None で記憶し、毎回の再推論を避ける。
_emb_cache: dict[str, tuple[float, "np.ndarray | None"]] = {}


def _embedding_for(path: str):
    """埋め込みを取得する（更新時刻が変わらない限りキャッシュを返す）。"""
    try:
        mtime = os.path.getmtime(path)
    except OSError:
        return None
    cached = _emb_cache.get(path)
    if cached is not None and cached[0] == mtime:
        return cached[1]
    emb = face_detector.get_embedding_from_file(path)
    _emb_cache[path] = (mtime, emb)
    return emb


def load_student_embeddings(students: list[dict]) -> list[dict]:
    """各学生の全顔写真から埋め込みリストを構築する。1枚も成功しない学生はスキップ。"""
    loaded = []
    seen_paths: set[str] = set()
    for s in students:
        embeddings = []
        face_images = s.get("face_images", [])

        # 後方互換: 旧 face_image_path 形式
        if not face_images and s.get("face_image_path"):
            face_images = [{"local_path": s["face_image_path"]}]

        for fi in face_images:
            path = fi.get("local_path", "")
            seen_paths.add(path)
            emb = _embedding_for(path)
            if emb is not None:
                embeddings.append(emb)
            else:
                logger.debug("  写真スキップ: %s (%s)", fi.get("label", ""), path)

        if embeddings:
            s["embeddings"] = embeddings
            # 複数写真をセントロイド（正規化済み平均ベクトル）に集約して照合精度を向上
            s["centroid"] = compute_centroid(embeddings)
            loaded.append(s)
            logger.info("  埋め込み読み込み: %s (%s) — %d枚 → セントロイド生成済み",
                        s["name"], s["student_number"], len(embeddings))
        else:
            logger.warning("  全写真で顔検出失敗（スキップ）: %s", s["name"])

    # 削除された写真のキャッシュを掃除（メモリ保護）
    for path in list(_emb_cache):
        if path not in seen_paths:
            del _emb_cache[path]
    return loaded


def frame_to_jpeg_bytes(frame: np.ndarray) -> bytes:
    img = Image.fromarray(cv2.cvtColor(frame, cv2.COLOR_BGR2RGB))
    buf = io.BytesIO()
    img.save(buf, format="JPEG", quality=JPEG_QUALITY)
    return buf.getvalue()


def crop_face(frame: np.ndarray, bbox: dict) -> np.ndarray:
    x, y, w, h = bbox["x"], bbox["y"], bbox["w"], bbox["h"]
    fh, fw = frame.shape[:2]
    return frame[max(0, y):min(fh, y + h), max(0, x):min(fw, x + w)]


def main_loop(device, rgb_queue, depth_queue, session, students):
    attended: set[int] = set()
    cooldowns: dict[int, float] = {}
    spoof_log_cooldowns: dict[int, float] = {}  # なりすましログのスパム防止（学生別）
    last_unknown_log = 0.0                       # 識別不能ログのスパム防止（全体）
    last_cache_update = time.time()
    frame_count = 0
    last_faces: list[dict] = []  # 前回の検出結果をキャッシュ

    # テンポラル投票: 同一学生が TEMPORAL_VOTE_MIN フレーム連続して照合されたら出席処理へ進む。
    # 1フレームの瞬間的な誤マッチ（横顔・目閉じ・照明変化）による誤記録を防ぐ。
    consecutive_hits: dict[int, int] = {}  # student_id → 連続マッチフレーム数

    logger.info("カメラ起動完了。Ctrl+C または 'q' で終了。")

    while True:
        # キャッシュ更新
        if time.time() - last_cache_update > STUDENT_CACHE_TTL_SEC:
            nonlocal_session = api_client.get_active_session()
            if nonlocal_session is None:
                logger.warning("セッションが終了しました。終了します。")
                break
            # 次のコマに切り替わっていたら、前セッションの出席状態を引き継がない
            if session is None or nonlocal_session["id"] != session["id"]:
                logger.info("セッション切替を検出（%s）→ 出席状態をリセット",
                            nonlocal_session.get("course_name", ""))
                attended.clear()
                cooldowns.clear()
                spoof_log_cooldowns.clear()
                consecutive_hits.clear()
            session = nonlocal_session
            raw_students = api_client.get_students()
            students[:] = load_student_embeddings(raw_students)
            last_cache_update = time.time()
            logger.info("キャッシュ更新: %d 名", len(students))

        in_rgb   = rgb_queue.tryGet()
        in_depth = depth_queue.tryGet()

        if in_rgb is None or in_depth is None:
            time.sleep(0.005)  # フレーム待ち。スリープなしだと CPU 1コアを空回りで使い切る
            continue

        rgb_frame   = in_rgb.getCvFrame()
        depth_frame = in_depth.getFrame()
        frame_count += 1

        # DETECT_INTERVAL フレームに1回だけ顔検出・照合を実行（表示は毎フレーム）。
        # fresh_detection: このフレームで実際に検出を行ったか。
        # キャッシュ流用フレームで投票カウンタを進めると「1回の検出が
        # DETECT_INTERVAL 回分の票」になり投票が無意味になるため、
        # カウンタ更新・ログ出力は検出フレームに限定する。
        if frame_count % DETECT_INTERVAL == 0:
            faces = face_detector.detect_and_embed(rgb_frame)
            last_faces = faces
            fresh_detection = True
        else:
            faces = last_faces
            fresh_detection = False

        # 深度マップを可視化（デバッグ表示用）
        depth_vis = None
        if DEBUG_DISPLAY:
            depth_norm = cv2.normalize(depth_frame, None, 0, 255, cv2.NORM_MINMAX, dtype=cv2.CV_8U)
            depth_vis  = cv2.applyColorMap(depth_norm, cv2.COLORMAP_JET)
            depth_vis  = cv2.resize(depth_vis, (CAMERA_WIDTH, CAMERA_HEIGHT))

        # テンポラル投票: 今フレームでマッチした学生 ID を追跡し、
        # 前フレームでマッチしなかった学生のカウンタをリセットする。
        matched_sids_this_frame: set[int] = set()

        for face in faces:
            if face["w"] < MIN_FACE_SIZE or face["h"] < MIN_FACE_SIZE:
                continue

            # Step 1: 誰かを識別
            embedding = face.get("embedding")
            if embedding is None:
                continue

            matched_student, dist = face_detector.find_best_match(embedding, students)

            if matched_student is None:
                if fresh_detection:
                    logger.info("識別不能  距離=%.3f", dist)
                # 未登録者として検出ログに記録（一定間隔でスロットリング）。
                # クロップは描画より先に行う（証跡画像に枠や文字を焼き込まない）。
                if time.time() - last_unknown_log >= DETECTION_LOG_COOLDOWN_SEC:
                    last_unknown_log = time.time()
                    unknown_crop = crop_face(rgb_frame, face)
                    api_client.report_detection(
                        reason="unknown",
                        session_id=session["id"] if session else None,
                        similarity_score=dist,
                        captured_image_bytes=frame_to_jpeg_bytes(unknown_crop),
                    )
                _draw_unknown(rgb_frame, face)
                continue

            if fresh_detection:
                logger.info("識別: %s (%s)  距離=%.3f  類似度=%.1f%%",
                            matched_student["name"], matched_student["student_number"],
                            dist, (1 - dist) * 100)

            sid = matched_student["id"]

            if time.time() - cooldowns.get(sid, 0) < COOLDOWN_SEC:
                if fresh_detection:
                    logger.info("  → スキップ（クールダウン中）")
                _draw_match(rgb_frame, face, matched_student["name"], dist, duplicate=True)
                continue

            if sid in attended:
                if fresh_detection:
                    logger.info("  → スキップ（本日出席済み）")
                _draw_match(rgb_frame, face, matched_student["name"], dist, duplicate=True)
                continue

            # Step 1.5: テンポラル投票 ─ 連続 TEMPORAL_VOTE_MIN 回の実検出で一致したら確定
            #   1回の瞬間的な誤マッチ（斜め顔・まばたき・照明ちらつき）を除去する。
            #   カウンタは検出を実行したフレーム（fresh_detection）でのみ進める。
            matched_sids_this_frame.add(sid)
            if fresh_detection:
                consecutive_hits[sid] = consecutive_hits.get(sid, 0) + 1
            hits = consecutive_hits.get(sid, 0)

            if hits < TEMPORAL_VOTE_MIN:
                if fresh_detection:
                    logger.info("  → 投票待ち (%d/%d 回): %s",
                                hits, TEMPORAL_VOTE_MIN, matched_student["name"])
                _draw_match(rgb_frame, face, matched_student["name"], dist)
                continue  # まだ確定しない

            # TEMPORAL_VOTE_MIN 回連続確認できた → 出席処理へ進む
            consecutive_hits[sid] = 0  # カウンタをリセット（二重送信防止）
            logger.info("  → %d 回連続確認 → 出席処理へ: %s",
                        TEMPORAL_VOTE_MIN, matched_student["name"])

            # Step 2: 識別後に深度センサーでなりすまし確認
            bbox_tuple = (face["x"], face["y"], face["w"], face["h"])
            is_live, std_dev = is_live_face(depth_frame, bbox_tuple)

            logger.info("  → 深度チェック: std=%.1f mm  %s",
                        std_dev, "生体 OK ✓" if is_live else "なりすまし NG ✗")

            if not is_live:
                # なりすまし疑いとして検出ログに記録（学生別にスロットリング）。
                # クロップは描画より先に行う（証跡画像に枠や文字を焼き込まない）。
                if time.time() - spoof_log_cooldowns.get(sid, 0) >= DETECTION_LOG_COOLDOWN_SEC:
                    spoof_log_cooldowns[sid] = time.time()
                    spoof_crop = crop_face(rgb_frame, face)
                    api_client.report_detection(
                        reason="spoofing",
                        session_id=session["id"] if session else None,
                        matched_student_id=sid,
                        similarity_score=dist,
                        depth_std_dev=std_dev,
                        captured_image_bytes=frame_to_jpeg_bytes(spoof_crop),
                    )
                _draw_rejected(rgb_frame, face,
                               f"{matched_student['name']} SPOOFING std={std_dev:.0f}mm")
                continue

            cooldowns[sid] = time.time()

            face_crop = crop_face(rgb_frame, face)
            face_bytes = frame_to_jpeg_bytes(face_crop)
            result = api_client.record_attendance(
                student_id=sid,
                session_id=session["id"],
                similarity_score=dist,
                captured_image_bytes=face_bytes,
            )

            if result is None:
                logger.warning("  → 出席記録 失敗（API エラー）: %s", matched_student["name"])
            elif result.get("queued"):
                # 通信不能でオフライン退避済み。接続回復後に自動再送されるので
                # 本セッションでは出席済み扱いにして二重送信を防ぐ。
                logger.info("  → オフライン退避（接続回復後に自動再送）: %s", matched_student["name"])
                attended.add(sid)
            elif result.get("duplicate"):
                logger.info("  → 出席済み（重複）: %s", matched_student["name"])
                attended.add(sid)
            else:
                logger.info("  → 出席記録 完了: %s (%s)",
                            matched_student["name"], matched_student["student_number"])
                attended.add(sid)

            _draw_match(rgb_frame, face, matched_student["name"], dist)

        # テンポラル投票: 今回の検出で見えなかった学生のカウンタをリセット
        # （一時的に顔が見えなくなった後の残カウントを防ぎ、再登場時に投票を最初からやり直す）
        if fresh_detection:
            for sid in list(consecutive_hits.keys()):
                if sid not in matched_sids_this_frame:
                    consecutive_hits[sid] = 0

        if DEBUG_DISPLAY:
            disp = cv2.resize(rgb_frame, (CAMERA_WIDTH, CAMERA_HEIGHT))
            if depth_vis is not None:
                # 右端に深度マップを小さく並べて表示
                dw, dh = CAMERA_WIDTH // 4, CAMERA_HEIGHT // 4
                dsmall = cv2.resize(depth_vis, (dw, dh))
                disp[0:dh, CAMERA_WIDTH - dw:CAMERA_WIDTH] = dsmall
                cv2.putText(disp, "Depth map", (CAMERA_WIDTH - dw + 2, 14),
                            cv2.FONT_HERSHEY_SIMPLEX, 0.5, (255, 255, 255), 1)
            cv2.imshow("出席管理システム", disp)
            if cv2.waitKey(1) & 0xFF == ord("q"):
                break

    cv2.destroyAllWindows()


def run(test_mode: bool = False) -> None:
    logger.info("=== 出席管理カメラ起動 ===")

    session = api_client.get_active_session()
    if session is None:
        logger.error("進行中のセッションが見つかりません。Web 管理画面でセッションを開始してください。")
        if not test_mode:
            sys.exit(1)

    if session:
        logger.info("セッション: %s  (%s)", session["course_name"], session["session_date"])

    logger.info("学生データを読み込み中...")
    raw_students = api_client.get_students()
    students = load_student_embeddings(raw_students)
    logger.info("%d 名の学生を読み込みました", len(students))

    if test_mode:
        logger.info("テストモード: OAK-D なしでロジック確認")
        _test_run(students, session)
        return

    import depthai as dai

    with dai.Pipeline(dai.Device()) as pipeline:
        device = pipeline.getDefaultDevice()
        logger.info("デバイス接続: %s", device.getDeviceName())

        cam_color = pipeline.create(dai.node.Camera).build(dai.CameraBoardSocket.CAM_A)
        cam_left  = pipeline.create(dai.node.Camera).build(dai.CameraBoardSocket.CAM_B)
        cam_right = pipeline.create(dai.node.Camera).build(dai.CameraBoardSocket.CAM_C)

        stereo = pipeline.create(dai.node.StereoDepth)
        stereo.setDefaultProfilePreset(dai.node.StereoDepth.PresetMode.FACE)
        stereo.setDepthAlign(dai.CameraBoardSocket.CAM_A)
        stereo.setLeftRightCheck(True)
        stereo.setOutputSize(CAMERA_WIDTH, CAMERA_HEIGHT)  # 16の倍数を明示指定

        cam_left.requestFullResolutionOutput().link(stereo.left)
        cam_right.requestFullResolutionOutput().link(stereo.right)

        cap = dai.ImgFrameCapability()
        cap.size.fixed((CAMERA_WIDTH, CAMERA_HEIGHT))
        cap.fps.fixed(CAMERA_FPS)
        rgb_out = cam_color.requestOutput(cap, False)
        logger.info("カメラ設定: %dx%d @ %dfps / 検出間隔: %dフレームに1回",
                    CAMERA_WIDTH, CAMERA_HEIGHT, CAMERA_FPS, DETECT_INTERVAL)

        rgb_queue   = rgb_out.createOutputQueue(maxSize=4, blocking=False)
        depth_queue = stereo.depth.createOutputQueue(maxSize=4, blocking=False)

        pipeline.start()
        main_loop(device, rgb_queue, depth_queue, session, students)

    logger.info("終了しました。")


def _draw_match(frame, bbox, name, dist, duplicate=False):
    x, y, w, h = bbox["x"], bbox["y"], bbox["w"], bbox["h"]
    color = (0, 200, 0) if not duplicate else (0, 165, 255)
    cv2.rectangle(frame, (x, y), (x + w, y + h), color, 2)
    label = f"{name} ({(1-dist)*100:.1f}%)" + (" [済]" if duplicate else "")
    cv2.putText(frame, label, (x, y - 8), cv2.FONT_HERSHEY_SIMPLEX, 0.6, color, 2)


def _draw_unknown(frame, bbox):
    x, y, w, h = bbox["x"], bbox["y"], bbox["w"], bbox["h"]
    cv2.rectangle(frame, (x, y), (x + w, y + h), (0, 0, 255), 2)
    cv2.putText(frame, "UNKNOWN", (x, y - 8), cv2.FONT_HERSHEY_SIMPLEX, 0.6, (0, 0, 255), 2)


def _draw_rejected(frame, bbox, label):
    x, y, w, h = bbox["x"], bbox["y"], bbox["w"], bbox["h"]
    cv2.rectangle(frame, (x, y), (x + w, y + h), (128, 0, 128), 2)
    cv2.putText(frame, label, (x, y - 8), cv2.FONT_HERSHEY_SIMPLEX, 0.5, (128, 0, 128), 2)


def _test_run(students, session):
    logger.info("--- テスト開始 ---")
    logger.info("登録学生数: %d", len(students))
    if session:
        logger.info("セッション ID: %d", session["id"])

    flat_depth = np.full((100, 100), 500, dtype=np.float32)
    live_depth = np.random.normal(500, 25, (100, 100)).astype(np.float32)

    ok_flat, sd_flat = is_live_face(flat_depth, (0, 0, 100, 100))
    ok_live, sd_live = is_live_face(live_depth, (0, 0, 100, 100))

    logger.info("深度テスト — 平面: live=%s std=%.1f mm (期待: False)", ok_flat, sd_flat)
    logger.info("深度テスト — 立体: live=%s std=%.1f mm (期待: True)",  ok_live, sd_live)

    assert not ok_flat, "平面が生体と判定されました"
    assert ok_live,     "立体が非生体と判定されました"

    logger.info("--- テスト合格 ---")


if __name__ == "__main__":
    parser = argparse.ArgumentParser(description="出席管理カメラスクリプト")
    parser.add_argument("--test", action="store_true", help="OAK-D なしでロジックテスト")
    args = parser.parse_args()
    run(test_mode=args.test)
