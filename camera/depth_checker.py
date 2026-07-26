"""
深度センサーによるなりすまし（写真・スマホ動画・印刷物）検出。

実際の顔は3D構造を持つ（鼻が突出、額・顎が後退）ので深度値のばらつきが大きい。
平面の写真やスクリーンは均一な深度値を持ち、ばらつきが小さい。

【v2 処理見直し — スマホ画面によるすり抜け対策】
旧処理は「顔バウンディングボックス全体の生の標準偏差」を使っていたため、
以下の穴があり、スマホ画面をかざすと生体判定を突破できてしまった:

  穴1: bbox は顔より一回り大きく、画面の縁や背景が混入する。
       平面の画面でも「画面と背景の深度段差」で std が跳ね上がり合格していた。
  穴2: 光沢・発光面のステレオ深度はスペックルノイズが多く、
       外れ値だけで std が閾値を超えることがある。

対策（ばらつき方式は維持したまま処理を修正）:
  ① 中央クロップ: bbox の中央部（鼻・頬・目のあたり）だけを評価し、
     輪郭外の背景・画面の縁の段差を除外する。
  ② ロバスト統計: 生の std ではなくパーセンタイル幅 (p90 - p10) を使い、
     ノイズ外れ値の影響を除去する。
  ③ 上限チェック: 顔中央部の凹凸は人間ならせいぜい十数cm。
     それを超えるばらつきは「段差の混入（=平面+背景）」なので拒否する。
  ④ 有効画素率: 画面はステレオマッチングが崩れて無効画素だらけになるため、
     有効率が低いパッチは判定不能として拒否する。
"""
import numpy as np
from config import (
    DEPTH_BBOX_SHRINK_RATIO,
    DEPTH_LIVENESS_MAX_MM,
    DEPTH_LIVENESS_THRESHOLD_MM,
    DEPTH_MIN_VALID_PIXELS,
    DEPTH_MIN_VALID_RATIO,
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
        threshold_mm: 生体判定のばらつき下限 (mm)

    Returns:
        (is_live, spread_mm)  spread_mm は顔中央部の深度ばらつき (p90 - p10)
    """
    x, y, w, h = face_bbox

    # ① 顔中央部だけを評価する（bbox 周縁の背景・画面の縁の段差を除外）
    mx = int(w * DEPTH_BBOX_SHRINK_RATIO)
    my = int(h * DEPTH_BBOX_SHRINK_RATIO)

    fh, fw = depth_frame.shape[:2]
    x1 = max(0, x + mx)
    y1 = max(0, y + my)
    x2 = min(fw, x + w - mx)
    y2 = min(fh, y + h - my)

    if x2 <= x1 or y2 <= y1:
        return False, 0.0

    patch = depth_frame[y1:y2, x1:x2].astype(np.float32)

    # 有効値のみ使用: config.ini の深度有効範囲（既定 10cm〜2m）
    valid = patch[(patch > DEPTH_VALID_MIN_MM) & (patch < DEPTH_VALID_MAX_MM)]

    # ④ 有効画素の絶対数と比率をチェック
    #    （画面・光沢面はステレオが崩れて無効画素が急増する → 判定不能として拒否）
    if len(valid) < DEPTH_MIN_VALID_PIXELS:
        return False, 0.0
    if len(valid) / patch.size < DEPTH_MIN_VALID_RATIO:
        return False, 0.0

    # ② ノイズ外れ値に強いパーセンタイル幅で凹凸を測る
    p10, p90 = np.percentile(valid, (10.0, 90.0))
    spread = float(p90 - p10)

    # 平面（写真・スマホ画面）: ばらつきが小さすぎる → 拒否
    if spread < threshold_mm:
        return False, spread

    # ③ 人間の顔中央部としてあり得ない大きさのばらつき
    #    = 画面の縁・背景の段差が混入している（平面すり抜けの典型パターン）→ 拒否
    if spread > DEPTH_LIVENESS_MAX_MM:
        return False, spread

    return True, spread
