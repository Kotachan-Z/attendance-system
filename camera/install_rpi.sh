#!/usr/bin/env bash
# Raspberry Pi (Raspberry Pi OS 64-bit Bookworm) セットアップスクリプト
# 実行: bash install_rpi.sh
set -e

echo "=== 出席管理システム — Raspberry Pi セットアップ ==="

# --- システムパッケージ ---
sudo apt-get update
sudo apt-get install -y \
    python3-pip python3-venv \
    libopencv-dev python3-opencv \
    libatlas-base-dev libhdf5-dev \
    cmake git \
    udev

# --- OAK-D USB udev ルール ---
echo "OAK-D USB ルール設定..."
echo 'SUBSYSTEM=="usb", ATTRS{idVendor}=="03e7", MODE="0666"' \
    | sudo tee /etc/udev/rules.d/80-movidius.rules
sudo udevadm control --reload-rules && sudo udevadm trigger

# --- Python 仮想環境 ---
VENV_DIR="$HOME/attendance-venv"
python3 -m venv "$VENV_DIR"
source "$VENV_DIR/bin/activate"

pip install --upgrade pip wheel

# --- カメラ処理の依存ライブラリ（camera/requirements.txt にまとめてある）---
#     depthai / insightface / onnxruntime / opencv-python / numpy / requests / Pillow
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
pip install -r "$SCRIPT_DIR/requirements.txt"

echo ""
echo "=== セットアップ完了 ==="
echo ""
echo "使い方:"
echo "  source $VENV_DIR/bin/activate"
echo "  cd /path/to/attendance-systemV2/camera"
echo "  export LARAVEL_API_URL=http://<Laravel-IP>:8000/api"
echo "  export CAMERA_API_TOKEN=<your-token>"
echo "  python main.py"
echo ""
echo "初回テスト（OAK-D なし）:"
echo "  python main.py --test"
echo ""
echo "------------------------------------------------------------"
echo " 【重要】Laravel スケジューラの登録（同じ Pi で Laravel を動かす場合）"
echo "------------------------------------------------------------"
echo " 出席セッションの自動生成・欠席の自動記録は、Laravel の"
echo " スケジューラ（cron）で動きます。Laravel 側で 1度だけ実行してください:"
echo ""
echo "   cd /path/to/attendance-systemV2/app"
echo "   bash setup_scheduler.sh"
echo ""
echo " ※ 詳しい説明は app/setup_scheduler.sh の先頭コメントを参照。"
echo "------------------------------------------------------------"
