#!/usr/bin/env python3
"""
InsightFace モデルを事前にダウンロードするスクリプト。

初回起動時にネット接続が必要。このスクリプトを先に実行しておくと、
教室など接続が不安定な環境でも main.py がすぐ起動できる。

使い方:
    python download_models.py              # buffalo_l（高精度・既定）
    python download_models.py --model buffalo_s  # 軽量モデル
"""
import argparse
import logging
import sys
from pathlib import Path

logging.basicConfig(level=logging.INFO, format="%(asctime)s %(message)s")
logger = logging.getLogger(__name__)


def download(model_name: str) -> None:
    logger.info("InsightFace モデルをダウンロード中: %s", model_name)
    logger.info("（初回のみ時間がかかります。ネット接続が必要です）")
    try:
        from insightface.app import FaceAnalysis
        app = FaceAnalysis(name=model_name, providers=["CPUExecutionProvider"])
        app.prepare(ctx_id=-1, det_size=(640, 640))
        logger.info("✓ ダウンロード完了: %s", model_name)

        # キャッシュ先を表示
        cache_dir = Path.home() / ".insightface" / "models" / model_name
        if cache_dir.exists():
            size_mb = sum(f.stat().st_size for f in cache_dir.rglob("*") if f.is_file()) / 1024 / 1024
            logger.info("  保存先: %s  (%.1f MB)", cache_dir, size_mb)
    except ImportError:
        logger.error("insightface がインストールされていません。pip install insightface を実行してください。")
        sys.exit(1)
    except Exception as e:
        logger.error("ダウンロード失敗: %s", e)
        sys.exit(1)


if __name__ == "__main__":
    parser = argparse.ArgumentParser(description="InsightFace モデルの事前ダウンロード")
    parser.add_argument("--model", default="buffalo_l",
                        choices=["buffalo_l", "buffalo_s"],
                        help="ダウンロードするモデル (default: buffalo_l)")
    args = parser.parse_args()
    download(args.model)
