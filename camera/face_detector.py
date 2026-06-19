"""
InsightFace を使った顔検出・埋め込み生成・照合。
detect_and_embed() で検出と埋め込みを一括取得し、
find_best_match() で曖昧さチェック付き照合を行う。
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


def cosine_distance(a: np.ndarray, b: np.ndarray) -> float:
    a_norm = a / (np.linalg.norm(a) + 1e-10)
    b_norm = b / (np.linalg.norm(b) + 1e-10)
    return float(1.0 - np.dot(a_norm, b_norm))


def find_best_match(
    query_embedding: np.ndarray,
    students: list[dict],
) -> tuple[dict | None, float]:
    """
    クエリ埋め込みと学生リストを照合する。
    各学生の全写真の中で最小距離を求め、全学生を距離順にソート。

    - FACE_MATCH_THRESHOLD を超えたら未登録と判定
    - 1位と2位の距離差が FACE_AMBIGUITY_MARGIN 未満なら曖昧として拒否
      （二人が似ていてどちらか分からない状態を防ぐ）
    """
    if not students:
        return None, float("inf")

    results: list[tuple[float, dict]] = []
    for student in students:
        embeddings = student.get("embeddings", [])
        if not embeddings:
            continue
        best = min(cosine_distance(query_embedding, emb) for emb in embeddings)
        results.append((best, student))

    if not results:
        return None, float("inf")

    results.sort(key=lambda x: x[0])
    best_dist, best_student = results[0]

    if best_dist > FACE_MATCH_THRESHOLD:
        return None, best_dist

    if len(results) >= 2:
        second_dist = results[1][0]
        gap = second_dist - best_dist
        if gap < FACE_AMBIGUITY_MARGIN:
            logger.info(
                "照合曖昧: %s(%.3f) vs %s(%.3f) 差=%.3f → 拒否",
                best_student["name"], best_dist,
                results[1][1]["name"], second_dist, gap,
            )
            return None, best_dist

    return best_student, best_dist
