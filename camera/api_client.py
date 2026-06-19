"""
Laravel API との通信クライアント。

リトライ設計（出席記録を取りこぼさないための2段構え）:
  1. 即時リトライ … 一時的な通信失敗（タイムアウト/接続断/5xx）は
     requests.Session + urllib3 Retry が指数バックオフで自動再試行する。
  2. オフライン退避 … 即時リトライでも送れなかった出席記録は
     バックグラウンドのキューに退避し、別スレッドが接続回復まで再送し続ける。
     （4xx＝退学/未履修/不正画像などの恒久エラーは再送しても無駄なので破棄）

サーバ側は重複登録を弾く（同一 student×session は2回目以降 duplicate 応答）ため、
退避分を二重に送っても出席が重複することはない。
"""
import base64
import logging
import threading
import time
from collections import deque

import requests
from requests.adapters import HTTPAdapter

try:  # urllib3 は requests の同梱依存。バージョン差を吸収。
    from urllib3.util.retry import Retry
except ImportError:  # pragma: no cover
    from requests.packages.urllib3.util.retry import Retry  # type: ignore

from config import (
    API_RETRY_BACKOFF_SEC,
    API_RETRY_MAX,
    CAMERA_API_TOKEN,
    LARAVEL_API_URL,
    OFFLINE_QUEUE_MAX,
)

logger = logging.getLogger(__name__)

HEADERS = {
    "Authorization": f"Bearer {CAMERA_API_TOKEN}",
    "Accept": "application/json",
}
TIMEOUT = 10

# バックグラウンド再送の最大間隔（秒）。接続が落ちている間はここまで伸ばす。
_MAX_BACKOFF_SEC = 30.0


def _build_session() -> requests.Session:
    """一時的失敗を自動リトライする HTTP セッションを作る。"""
    retry = Retry(
        total=max(0, API_RETRY_MAX),
        connect=max(0, API_RETRY_MAX),
        read=max(0, API_RETRY_MAX),
        backoff_factor=max(0.0, API_RETRY_BACKOFF_SEC),
        status_forcelist=(500, 502, 503, 504),
        allowed_methods=frozenset({"GET", "POST"}),
        raise_on_status=False,
    )
    adapter = HTTPAdapter(max_retries=retry)
    session = requests.Session()
    session.headers.update(HEADERS)
    session.mount("http://", adapter)
    session.mount("https://", adapter)
    return session


_session = _build_session()


# ── 読み取り系（GET）──────────────────────────────────────────────────
def get_active_session() -> dict | None:
    """進行中のセッションを返す。なければ None。"""
    try:
        resp = _session.get(f"{LARAVEL_API_URL}/sessions/active", timeout=TIMEOUT)
        if resp.status_code == 200:
            return resp.json()
        return None
    except Exception as e:
        logger.warning("セッション取得失敗: %s", e)
        return None


def get_students() -> list[dict]:
    """顔画像パス付きの学生リストを返す。"""
    try:
        resp = _session.get(f"{LARAVEL_API_URL}/students", timeout=TIMEOUT)
        resp.raise_for_status()
        return resp.json()
    except Exception as e:
        logger.warning("学生リスト取得失敗: %s", e)
        return []


# ── 書き込み系（POST = 出席記録）─────────────────────────────────────
def _post_attendance(payload: dict) -> tuple[str, dict | None]:
    """
    出席記録を1回送信する。即時リトライは _session が内部で行う。
    戻り値: ("ok", data) 成功 / ("drop", data) 恒久エラー / ("retry", None) 通信不能・5xx
    """
    try:
        resp = _session.post(f"{LARAVEL_API_URL}/attendance", json=payload, timeout=TIMEOUT)
    except requests.exceptions.RequestException as e:
        logger.debug("attendance POST 通信失敗: %s", e)
        return "retry", None

    try:
        data = resp.json()
    except ValueError:
        data = {}

    if resp.status_code in (200, 201):
        return "ok", data
    if 400 <= resp.status_code < 500:
        # 退学・未履修・不正画像・認証エラーなど。再送しても結果は変わらない。
        return "drop", data
    return "retry", None  # 即時リトライ後も残る 5xx → 後でまた試す


def record_attendance(
    student_id: int,
    session_id: int,
    similarity_score: float,
    captured_image_bytes: bytes,
) -> dict | None:
    """
    出席をAPIに記録する。captured_image_bytes: JPEG/PNG バイナリ。

    戻り値:
      - 成功時: サーバ応答 dict（duplicate=True を含むこともある）
      - 通信不能でキュー退避した場合: {"queued": True}
      - 恒久エラーで破棄した場合: None
    """
    b64 = base64.b64encode(captured_image_bytes).decode("utf-8")
    payload = {
        "student_id":            student_id,
        "attendance_session_id": session_id,
        "similarity_score":      round(similarity_score, 4),
        "captured_image":        b64,
    }

    status, data = _post_attendance(payload)
    if status == "ok":
        return data
    if status == "drop":
        logger.warning("出席記録 失敗（恒久エラー・破棄）: %s", data)
        return None

    # 通信不能 → オフラインキューに退避して後で自動再送
    if _offline_queue.put(payload):
        logger.warning("出席記録 通信失敗 → オフライン退避（接続回復後に自動再送）")
        return {"queued": True}

    logger.error("出席記録 通信失敗（退避も不可）: student_id=%s", student_id)
    return None


def report_detection(
    reason: str,
    session_id: int | None = None,
    matched_student_id: int | None = None,
    similarity_score: float | None = None,
    depth_std_dev: float | None = None,
    captured_image_bytes: bytes | None = None,
) -> bool:
    """
    弾いた検出イベント（reason="spoofing" なりすまし疑い / "unknown" 識別不能）を
    サーバに記録する。診断用ログなので best-effort（失敗しても出席処理は止めない）。

    戻り値: 記録できたら True。
    """
    payload: dict = {"reason": reason}
    if session_id is not None:
        payload["attendance_session_id"] = session_id
    if matched_student_id is not None:
        payload["matched_student_id"] = matched_student_id
    if similarity_score is not None:
        payload["similarity_score"] = round(similarity_score, 4)
    if depth_std_dev is not None:
        payload["depth_std_dev"] = round(float(depth_std_dev), 1)
    if captured_image_bytes is not None:
        payload["captured_image"] = base64.b64encode(captured_image_bytes).decode("utf-8")

    try:
        resp = _session.post(f"{LARAVEL_API_URL}/detections", json=payload, timeout=TIMEOUT)
        if resp.status_code in (200, 201):
            return True
        logger.debug("検出ログ送信 失敗: status=%s", resp.status_code)
        return False
    except requests.exceptions.RequestException as e:
        logger.debug("検出ログ送信 通信失敗: %s", e)
        return False


class _OfflineQueue:
    """通信不能時に出席記録を退避し、接続回復まで再送するバックグラウンドキュー。"""

    def __init__(self, maxlen: int):
        self._enabled = maxlen > 0
        self._dq: deque[dict] = deque(maxlen=maxlen if self._enabled else 1)
        self._cv = threading.Condition()
        self._worker: threading.Thread | None = None

    def put(self, payload: dict) -> bool:
        """退避する。退避が無効なら False。"""
        if not self._enabled:
            return False
        with self._cv:
            if len(self._dq) >= self._dq.maxlen:
                dropped = self._dq.popleft()
                logger.warning("オフラインキュー満杯。最古の記録を破棄: student_id=%s",
                               dropped.get("student_id"))
            self._dq.append(payload)
            self._cv.notify()
        self._ensure_worker()
        return True

    def _ensure_worker(self) -> None:
        with self._cv:
            if self._worker is None or not self._worker.is_alive():
                self._worker = threading.Thread(
                    target=self._run, name="attendance-uploader", daemon=True
                )
                self._worker.start()

    def _run(self) -> None:
        backoff = max(0.5, API_RETRY_BACKOFF_SEC)
        while True:
            with self._cv:
                while not self._dq:
                    self._cv.wait()
                payload = self._dq.popleft()
                remaining = len(self._dq)

            status, _ = _post_attendance(payload)

            if status in ("ok", "drop"):
                if status == "ok":
                    logger.info("オフライン退避分の再送に成功（残り %d 件）", remaining)
                else:
                    logger.warning("退避分の再送を断念（恒久エラー）: student_id=%s",
                                   payload.get("student_id"))
                backoff = max(0.5, API_RETRY_BACKOFF_SEC)
                continue

            # まだ送れない → 先頭に戻してバックオフ後に再挑戦
            with self._cv:
                self._dq.appendleft(payload)
            time.sleep(min(backoff, _MAX_BACKOFF_SEC))
            backoff = min(backoff * 2, _MAX_BACKOFF_SEC)

    def pending(self) -> int:
        with self._cv:
            return len(self._dq)


_offline_queue = _OfflineQueue(OFFLINE_QUEUE_MAX)


def pending_uploads() -> int:
    """再送待ちの出席記録の件数（監視・ログ用）。"""
    return _offline_queue.pending()
