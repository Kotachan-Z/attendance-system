"""
InsightFace を使った顔検出・埋め込み生成・照合。
detect_and_embed() で検出と埋め込みを一括取得し、
find_best_match() でセントロイド照合＋曖昧さチェックを行う。

【v2 精度改善】
  ① セントロイド比較: 複数枚の登録写真を平均ベクトル(L2 正規化済み)に集約し、
    ばらつきを吸収してロバストな照合を実現。
    旧: 全写真の中で最小距離の1枚だけで判定（偶然の誤マッチに弱い）
    新: 全写真を平均化したセントロイドと比較（1枚の外れ値に左右されない）

  ② しきい値厳格化: buffalo_l の同一人物距離は通常 0.1〜0.3。
    旧 match_threshold=0.50 は別人も通過しやすかった → 0.40 に変更。

  ③ 曖昧マージン拡大: 0.08 → 0.10
    似た顔の学生が複数いても誤照合を防ぐ安全マージン。
"""
import logging
import cv2
import numpy as np
from insightface.app import FaceAnalysis
from config import (
    DET_SIZE,
    FACE_AMBIGUITY_MARGIN,
    FACE_MATCH_THRESHOLD,
    INSIGHTFACE_MODEL,
    MIN_FACE_CONFIDENCE,
)

logger = logging.getLogger(__name__)

_app: FaceAnalysis | None = None


def _get_app() -> FaceAnalysis:
    global _app
    if _app is None:
        _app = FaceAnalysis(name=INSIGHTFACE_MODEL, providers=["CPUExecutionProvider"])
        _app.prepare(ctx_id=-1, det_size=(DET_SIZE, DET_SIZE))
        logger.info("InsightFace モデル読み込み完了: %s", INSIGHTFACE_MODEL)
    return _app


def detect_and_embed(frame: np.ndarray) -> list[dict]:
    """BGR フレームから顔検出 + 埋め込みを一括取得する。"""
    try:
        rgb = cv2.cvtColor(frame, cv2.COLOR_BGR2RGB)
        faces = _get_app().get(rgb)
        result = []
        for face in faces:
            if float(face.det_score) < MIN_FACE_CONFIDENCE:
                continue
            x1, y1, x2, y2 = [int(v) for v in face.bbox]
            result.append({
                "x": x1, "y": y1,
                "w": x2 - x1, "h": y2 - y1,
                "embedding": face.embedding,
                "confidence": float(face.det_score),
            })
        return result
    except Exception as e:
        logger.debug("顔検出失敗: %s", e)
        return []


def get_embedding_from_file(image_path: str) -> np.ndarray | None:
    """ファイルパスから埋め込みベクトルを取得する。最大面積の顔を使用。"""
    try:
        img = cv2.imread(image_path)
        if img is None:
            raise ValueError(f"画像を読み込めません: {image_path}")
        rgb = cv2.cvtColor(img, cv2.COLOR_BGR2RGB)
        faces = _get_app().get(rgb)
        if not faces:
            return None
        face = max(faces, key=lambda f: (f.bbox[2] - f.bbox[0]) * (f.bbox[3] - f.bbox[1]))
        return face.embedding
    except Exception as e:
        logger.debug("ファイルから埋め込み取得失敗 (%s): %s", image_path, e)
        return None


def compute_centroid(embeddings: list[np.ndarray]) -> np.ndarray:
    """
    複数の埋め込みベクトルをセントロイド（平均・L2 正規化済み）に集約する。

    L2 正規化してから平均を取ることで、各写真の「向き」を等重みで合成できる。
    正規化前に平均すると大きさが異なるベクトルに引きずられるため、正規化後の平均が正確。
    """
    normed = []
    for emb in embeddings:
        norm = np.linalg.norm(emb)
        if norm > 1e-10:
            normed.append(emb / norm)
    if not normed:
        # フォールバック: 正規化できないなら元ベクトルを平均
        mean_emb = np.mean(embeddings, axis=0)
    else:
        mean_emb = np.mean(normed, axis=0)
    # 最終的にも L2 正規化して単位ベクトルにする
    final_norm = np.linalg.norm(mean_emb)
    if final_norm > 1e-10:
        mean_emb = mean_emb / final_norm
    return mean_emb


def cosine_distance(a: np.ndarray, b: np.ndarray) -> float:
    """コサイン距離（0=同一方向, 2=逆方向）。どちらも L2 正規化済み前提。"""
    a_norm = a / (np.linalg.norm(a) + 1e-10)
    b_norm = b / (np.linalg.norm(b) + 1e-10)
    return float(1.0 - np.dot(a_norm, b_norm))


def find_best_match(
    query_embedding: np.ndarray,
    students: list[dict],
) -> tuple[dict | None, float]:
    """
    クエリ埋め込みと学生リストを照合する。

    【改善後の照合ロジック】
    各学生は登録時に全写真の平均ベクトル（セントロイド）を持つ。
    クエリとセントロイドのコサイン距離で比較する。

    - centroid が利用可能ならセントロイドと比較（精度優先）
    - centroid がない場合のみ個別埋め込みの最小距離にフォールバック（後方互換）
    - FACE_MATCH_THRESHOLD を超えたら未登録と判定
    - 1位と2位の距離差が FACE_AMBIGUITY_MARGIN 未満なら曖昧として拒否
    """
    if not students:
        return None, float("inf")

    results: list[tuple[float, dict]] = []
    for student in students:
        centroid = student.get("centroid")
        if centroid is not None:
            # ① セントロイド比較（推奨）
            dist = cosine_distance(query_embedding, centroid)
        else:
            # フォールバック: 旧来の最小距離方式
            embeddings = student.get("embeddings", [])
            if not embeddings:
                continue
            dist = min(cosine_distance(query_embedding, emb) for emb in embeddings)
        results.append((dist, student))

    if not results:
        return None, float("inf")

    results.sort(key=lambda x: x[0])
    best_dist, best_student = results[0]

    # ② しきい値チェック（config で 0.40 に厳格化）
    if best_dist > FACE_MATCH_THRESHOLD:
        return None, best_dist

    # ③ 曖昧さチェック（config で 0.10 に拡大）
    if len(results) >= 2:
        second_dist = results[1][0]
        gap = second_dist - best_dist
        if gap < FACE_AMBIGUITY_MARGIN:
            logger.info(
                "照合曖昧: %s(%.3f) vs %s(%.3f) 差=%.3f < margin=%.3f → 拒否",
                best_student["name"], best_dist,
                results[1][1]["name"], second_dist,
                gap, FACE_AMBIGUITY_MARGIN,
            )
            return None, best_dist

    return best_student, best_dist
