"""
深度センサーによるなりすまし（写真・スマホ動画・印刷物）検出。

実際の顔は3D構造を持つ（鼻が突出、額・顎が後退）ので深度値の標準偏差が大きい。
平面の写真やスクリーンは均一な深度値を持ち、標準偏差が小さい。
"""
import numpy as np
from config import (
    DEPTH_LIVENESS_THRESHOLD_MM,
    DEPTH_MIN_VALID_PIXELS,
    DEPTH_VALID_MAX_MM,
    DEPTH_VALID_MIN_MM,
)


def is_live_face(
    depth_frame: np.ndarray,
    face_bbox: tuple[int, int, int, int],
    threshold_mm: float = DEPTH_LIVENESS_THRESHOLD_MM,
) -> tuple[bool, float]:
    """
    Args:
        depth_frame: OAK-D の depth フレーム (mm 単位, uint16 or float32, HxW)
        face_bbox:   (x, y, w, h) — RGB フレーム上の顔領域
        threshold_mm: 生体判定の標準偏差閾値 (mm)

    Returns:
        (is_live, std_dev_mm)
    """
    x, y, w, h = face_bbox

    # 顔領域をクロップ（フレーム境界を超えないようにクリップ）
    fh, fw = depth_frame.shape[:2]
    x1 = max(0, x)
    y1 = max(0, y)
    x2 = min(fw, x + w)
    y2 = min(fh, y + h)

    patch = depth_frame[y1:y2, x1:x2].astype(np.float32)

    # 有効値のみ使用: config.ini の深度有効範囲（既定 10cm〜2m）
    valid = patch[(patch > DEPTH_VALID_MIN_MM) & (patch < DEPTH_VALID_MAX_MM)]

    if len(valid) < DEPTH_MIN_VALID_PIXELS:
        # 有効ピクセルが少なすぎる（遠すぎる・オクルージョン）
        return False, 0.0

    std_dev = float(np.std(valid))
    return std_dev > threshold_mm, std_dev
