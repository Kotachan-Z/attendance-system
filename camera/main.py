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
)
from depth_checker import is_live_face

logging.basicConfig(
    level=logging.INFO,
    format="%(asctime)s [%(levelname)s] %(message)s",
    datefmt="%H:%M:%S",
)
logger = logging.getLogger(__name__)


def load_student_embeddings(students: list[dict]) -> list[dict]:
    """各学生の全顔写真から埋め込みリストを構築する。1枚も成功しない学生はスキップ。"""
    loaded = []
    for s in students:
        embeddings = []
        face_images = s.get("face_images", [])

        # 後方互換: 旧 face_image_path 形式
        if not face_images and s.get("face_image_path"):
            face_images = [{"local_path": s["face_image_path"]}]

        for fi in face_images:
            path = fi.get("local_path", "")
            emb = face_detector.get_embedding_from_file(path)
            if emb is not None:
                embeddings.append(emb)
            else:
                logger.debug("  写真スキップ: %s (%s)", fi.get("label", ""), path)

        if embeddings:
            s["embeddings"] = embeddings
            loaded.append(s)
            logger.info("  埋め込み読み込み: %s (%s) — %d枚",
                        s["name"], s["student_number"], len(embeddings))
        else:
            logger.warning("  全写真で顔検出失敗（スキップ）: %s", s["name"])
    return loaded


def create_pipeline():
    """depthai 3.x パイプライン構築: RGB + 深度（RGB に align 済み）"""
    import depthai as dai

    with dai.Pipeline(dai.Device()) as pipeline:
        device = pipeline.getDefaultDevice()

        # カラーカメラ (CAM_A = RGB)
        cam_color = pipeline.create(dai.node.Camera).build(dai.CameraBoardSocket.CAM_A)

        # モノカメラ (CAM_B = 左, CAM_C = 右)
        cam_left  = pipeline.create(dai.node.Camera).build(dai.CameraBoardSocket.CAM_B)
        cam_right = pipeline.create(dai.node.Camera).build(dai.CameraBoardSocket.CAM_C)

        # ステレオ深度（RGB に align）
        stereo = pipeline.create(dai.node.StereoDepth)
        stereo.setDefaultProfilePreset(dai.node.StereoDepth.PresetMode.HIGH_DENSITY)
        stereo.setDepthAlign(dai.CameraBoardSocket.CAM_A)
        stereo.setLeftRightCheck(True)
        stereo.setSubpixel(False)

        cam_left.requestFullResolutionOutput().link(stereo.left)
        cam_right.requestFullResolutionOutput().link(stereo.right)

        # RGB 出力（解像度・FPS は config.ini）
        cap = dai.ImgFrameCapability()
        cap.size.fixed((CAMERA_WIDTH, CAMERA_HEIGHT))
        cap.fps.fixed(CAMERA_FPS)
        rgb_out = cam_color.requestOutput(cap, False)

        # 出力キュー
        rgb_queue   = rgb_out.createOutputQueue(maxSize=4, blocking=False)
        depth_queue = stereo.depth.createOutputQueue(maxSize=4, blocking=False)

        pipeline.start()

        yield device, rgb_queue, depth_queue


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

    logger.info("カメラ起動完了。Ctrl+C または 'q' で終了。")

    while True:
        # キャッシュ更新
        if time.time() - last_cache_update > STUDENT_CACHE_TTL_SEC:
            nonlocal_session = api_client.get_active_session()
            if nonlocal_session is None:
                logger.warning("セッションが終了しました。終了します。")
                break
            session = nonlocal_session
            raw_students = api_client.get_students()
            students[:] = load_student_embeddings(raw_students)
            last_cache_update = time.time()
            logger.info("キャッシュ更新: %d 名", len(students))

        in_rgb   = rgb_queue.tryGet()
        in_depth = depth_queue.tryGet()

        if in_rgb is None or in_depth is None:
            continue

        rgb_frame   = in_rgb.getCvFrame()
        depth_frame = in_depth.getFrame()
        frame_count += 1

        # DETECT_INTERVAL フレームに1回だけ顔検出・照合を実行（表示は毎フレーム）
        if frame_count % DETECT_INTERVAL == 0:
            faces = face_detector.detect_and_embed(rgb_frame)
            last_faces = faces
        else:
            faces = last_faces

        # 深度マップを可視化（デバッグ表示用）
        depth_vis = None
        if DEBUG_DISPLAY:
            depth_norm = cv2.normalize(depth_frame, None, 0, 255, cv2.NORM_MINMAX, dtype=cv2.CV_8U)
            depth_vis  = cv2.applyColorMap(depth_norm, cv2.COLORMAP_JET)
            depth_vis  = cv2.resize(depth_vis, (CAMERA_WIDTH, CAMERA_HEIGHT))

        for face in faces:
            if face["w"] < MIN_FACE_SIZE or face["h"] < MIN_FACE_SIZE:
                continue

            # Step 1: 誰かを識別
            embedding = face.get("embedding")
            if embedding is None:
                continue

            matched_student, dist = face_detector.find_best_match(embedding, students)

            if matched_student is None:
                logger.info("識別不能  距離=%.3f", dist)
                _draw_unknown(rgb_frame, face)
                # 未登録者として検出ログに記録（一定間隔でスロットリング）
                if time.time() - last_unknown_log >= DETECTION_LOG_COOLDOWN_SEC:
                    last_unknown_log = time.time()
                    unknown_crop = crop_face(rgb_frame, face)
                    api_client.report_detection(
                        reason="unknown",
                        session_id=session["id"] if session else None,
                        similarity_score=dist,
                        captured_image_bytes=frame_to_jpeg_bytes(unknown_crop),
                    )
                continue

            logger.info("識別: %s (%s)  距離=%.3f  類似度=%.1f%%",
                        matched_student["name"], matched_student["student_number"],
                        dist, (1 - dist) * 100)

            sid = matched_student["id"]

            if time.time() - cooldowns.get(sid, 0) < COOLDOWN_SEC:
                logger.info("  → スキップ（クールダウン中）")
                _draw_match(rgb_frame, face, matched_student["name"], dist, duplicate=True)
                continue

            if sid in attended:
                logger.info("  → スキップ（本日出席済み）")
                _draw_match(rgb_frame, face, matched_student["name"], dist, duplicate=True)
                continue

            # Step 2: 識別後に深度センサーでなりすまし確認
            bbox_tuple = (face["x"], face["y"], face["w"], face["h"])
            is_live, std_dev = is_live_face(depth_frame, bbox_tuple)

            logger.info("  → 深度チェック: std=%.1f mm  %s",
                        std_dev, "生体 OK ✓" if is_live else "なりすまし NG ✗")

            if not is_live:
                _draw_rejected(rgb_frame, face,
                               f"{matched_student['name']} SPOOFING std={std_dev:.0f}mm")
                # なりすまし疑いとして検出ログに記録（学生別にスロットリング）
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
