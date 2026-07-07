"""
設定ローダ。

すべての閾値・パラメータは同じディレクトリの ``config.ini`` で管理します。
値を変更したいときは ``config.ini`` を編集して、スクリプトを再起動してください。

優先順位（上が強い）:
    1. 環境変数（あれば最優先。例: FACE_MATCH_THRESHOLD=0.45）
    2. config.ini の値
    3. ここに書かれたコード内デフォルト（config.ini が無い/壊れている場合の保険）
"""
import configparser
import os
from pathlib import Path

_INI_PATH = Path(__file__).with_name("config.ini")

_parser = configparser.ConfigParser(inline_comment_prefixes=("#", ";"))
# config.ini が存在しなくてもデフォルト値で動くようにする（read は失敗しても例外を出さない）
_parser.read(_INI_PATH, encoding="utf-8")


def _raw(section: str, key: str, env: str | None, default):
    """env → config.ini → default の順で生の値（文字列）を取得する。"""
    if env and (v := os.getenv(env)) is not None:
        return v
    if _parser.has_option(section, key):
        return _parser.get(section, key)
    return default


def _str(section, key, env, default) -> str:
    return str(_raw(section, key, env, default))


def _float(section, key, env, default) -> float:
    return float(_raw(section, key, env, default))


def _int(section, key, env, default) -> int:
    # "60" でも "60.0" でも受け付ける
    return int(float(_raw(section, key, env, default)))


def _bool(section, key, env, default) -> bool:
    return str(_raw(section, key, env, default)).strip().lower() in ("1", "true", "yes", "on")


# ── [api] Laravel 連携 ────────────────────────────────────────────────
LARAVEL_API_URL  = _str("api", "url",   "LARAVEL_API_URL",  "http://localhost:8000/api")
CAMERA_API_TOKEN = _str("api", "token", "CAMERA_API_TOKEN", "attendance-camera-secret-token-change-in-production")
# 通信リトライ（一時的な失敗への耐性）
API_RETRY_MAX         = _int("api", "retry_max",         "API_RETRY_MAX",         3)
API_RETRY_BACKOFF_SEC = _float("api", "retry_backoff_sec", "API_RETRY_BACKOFF_SEC", 0.5)
OFFLINE_QUEUE_MAX     = _int("api", "offline_queue_max",  "OFFLINE_QUEUE_MAX",     500)

# ── [face] 顔検出・照合 ───────────────────────────────────────────────
INSIGHTFACE_MODEL     = _str("face", "model",                    "INSIGHTFACE_MODEL",        "buffalo_l")
DET_SIZE              = _int("face", "detection_size",           "DET_SIZE",                 640)
MIN_FACE_CONFIDENCE   = _float("face", "min_detection_confidence", "MIN_FACE_CONFIDENCE",    0.70)
FACE_MATCH_THRESHOLD  = _float("face", "match_threshold",         "FACE_MATCH_THRESHOLD",     0.40)
FACE_AMBIGUITY_MARGIN = _float("face", "ambiguity_margin",        "FACE_AMBIGUITY_MARGIN",    0.10)
MIN_FACE_SIZE         = _int("face", "min_face_size",             "MIN_FACE_SIZE",            60)
# 「強い一致」と見なす距離の上限。これ以下なら別人の可能性が極めて低いので
#   テンポラル投票を少ない回数で確定してよい（速度と精度の両立）。
FACE_STRONG_MATCH_THRESHOLD = _float("face", "strong_match_threshold", "FACE_STRONG_MATCH_THRESHOLD", 0.30)

# ── [liveness] 深度なりすまし検出 ─────────────────────────────────────
DEPTH_LIVENESS_THRESHOLD_MM = _float("liveness", "depth_std_threshold_mm", "DEPTH_LIVENESS_THRESHOLD_MM", 15.0)
DEPTH_VALID_MIN_MM          = _float("liveness", "depth_valid_min_mm",     "DEPTH_VALID_MIN_MM",          100.0)
DEPTH_VALID_MAX_MM          = _float("liveness", "depth_valid_max_mm",     "DEPTH_VALID_MAX_MM",          2000.0)
DEPTH_MIN_VALID_PIXELS      = _int("liveness", "depth_min_valid_pixels",   "DEPTH_MIN_VALID_PIXELS",      100)

# ── [recognition] 運用パラメータ ─────────────────────────────────────
COOLDOWN_SEC          = _int("recognition", "cooldown_sec",          "COOLDOWN_SEC",          5)
STUDENT_CACHE_TTL_SEC = _int("recognition", "student_cache_ttl_sec", "STUDENT_CACHE_TTL_SEC", 30)
DETECT_INTERVAL       = _int("recognition", "detect_interval",       "DETECT_INTERVAL",       3)
# なりすまし疑い・識別不能ログの再記録までの最小間隔（秒）。同じ状況の連投を防ぐ。
DETECTION_LOG_COOLDOWN_SEC = _int("recognition", "detection_log_cooldown_sec", "DETECTION_LOG_COOLDOWN_SEC", 30)
# テンポラル投票: この回数だけ連続して同一学生が照合されたとき初めて出席処理へ進む。
#   1フレームの偶発的な誤マッチ（横顔・まばたき・照明変化）を排除する安全弁。
#   際どい一致（距離が strong_match_threshold より大きい）に適用する。
TEMPORAL_VOTE_MIN = _int("recognition", "temporal_vote_min", "TEMPORAL_VOTE_MIN", 3)
# 強い一致のときの投票数。確定を速くするための短縮版（誤マッチ耐性を残すため最低 2 推奨）。
TEMPORAL_VOTE_STRONG_MIN = _int("recognition", "temporal_vote_strong_min", "TEMPORAL_VOTE_STRONG_MIN", 2)

# ── [camera] カメラ設定 ───────────────────────────────────────────────
CAMERA_WIDTH  = _int("camera", "width",        "CAMERA_WIDTH",  1920)
CAMERA_HEIGHT = _int("camera", "height",       "CAMERA_HEIGHT", 1080)
CAMERA_FPS    = _int("camera", "fps",          "CAMERA_FPS",    30)
JPEG_QUALITY  = _int("camera", "jpeg_quality", "JPEG_QUALITY",  85)
DEBUG_DISPLAY = _bool("camera", "debug_display", "DEBUG_DISPLAY", False)
