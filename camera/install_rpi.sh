#!/usr/bin/env bash
# ============================================================
#  出席管理システム — Raspberry Pi セットアップスクリプト
#  対象: Raspberry Pi OS 64-bit Bookworm
# ============================================================
#  実行方法:
#    cd /path/to/attendance-systemV2/camera
#    bash install_rpi.sh
#
#  このスクリプトが行うこと:
#    1. システムパッケージのインストール
#    2. OAK-D USB udev ルールの設定
#    3. Python 仮想環境の作成 + 依存ライブラリのインストール
#    4. InsightFace モデルの事前ダウンロード
#    5. systemd サービスの登録（起動時に自動スタート）
# ============================================================
set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
VENV_DIR="$HOME/attendance-venv"
SERVICE_NAME="attendance-camera"
SERVICE_FILE="/etc/systemd/system/${SERVICE_NAME}.service"

echo "============================================================"
echo " 出席管理システム — Raspberry Pi セットアップ"
echo " カメラディレクトリ: $SCRIPT_DIR"
echo " Python 仮想環境:   $VENV_DIR"
echo "============================================================"
echo ""

# ── 1. システムパッケージ ──────────────────────────────────────
echo "[1/5] システムパッケージをインストール中..."
sudo apt-get update -qq
sudo apt-get install -y \
    python3-pip python3-venv \
    libopencv-dev python3-opencv \
    libatlas-base-dev libhdf5-dev \
    cmake git udev
echo "✓ システムパッケージ完了"
echo ""

# ── 2. OAK-D USB udev ルール ──────────────────────────────────
echo "[2/5] OAK-D USB ルールを設定中..."
echo 'SUBSYSTEM=="usb", ATTRS{idVendor}=="03e7", MODE="0666"' \
    | sudo tee /etc/udev/rules.d/80-movidius.rules > /dev/null
sudo udevadm control --reload-rules
sudo udevadm trigger
echo "✓ USB ルール設定完了"
echo ""

# ── 3. Python 仮想環境 + 依存ライブラリ ────────────────────────
echo "[3/5] Python 仮想環境を作成中: $VENV_DIR"
python3 -m venv "$VENV_DIR"
source "$VENV_DIR/bin/activate"

pip install --upgrade pip wheel --quiet
echo "  依存ライブラリをインストール中（depthai / insightface など）..."
pip install -r "$SCRIPT_DIR/requirements.txt" --quiet
echo "✓ Python 環境構築完了"
echo ""

# ── 4. InsightFace モデルの事前ダウンロード ──────────────────
echo "[4/5] InsightFace モデルをダウンロード中（buffalo_l）..."
echo "  ※ 初回のみ時間がかかります（約400MB）"
python "$SCRIPT_DIR/download_models.py" --model buffalo_l
echo "✓ モデルダウンロード完了"
echo ""

# ── 5. systemd サービス登録 ──────────────────────────────────
echo "[5/5] systemd サービスを登録中..."

PYTHON_BIN="$VENV_DIR/bin/python"

sudo tee "$SERVICE_FILE" > /dev/null <<EOF
[Unit]
Description=出席管理システム カメラプロセス (InsightFace + OAK-D)
After=network-online.target
Wants=network-online.target

[Service]
Type=simple
User=$USER
WorkingDirectory=$SCRIPT_DIR
ExecStart=$PYTHON_BIN main.py
Restart=on-failure
RestartSec=10
StandardOutput=journal
StandardError=journal
SyslogIdentifier=attendance-camera

[Install]
WantedBy=multi-user.target
EOF

sudo systemctl daemon-reload
sudo systemctl enable "$SERVICE_NAME"
echo "✓ systemd サービス登録完了"
echo ""

# ── 完了メッセージ ────────────────────────────────────────────
echo "============================================================"
echo " セットアップ完了！"
echo "============================================================"
echo ""
echo "【次のステップ】"
echo ""
echo "  1. config.ini を作成して API トークンなどを設定する:"
echo "       cp $SCRIPT_DIR/config.example.ini $SCRIPT_DIR/config.ini"
echo "       nano $SCRIPT_DIR/config.ini"
echo "       # url   = http://<Laravel-IPアドレス>:80/api"
echo "       # token = <Laravel 側 CAMERA_API_TOKEN と同じ値>"
echo ""
echo "  2. 動作テスト（OAK-D なしでロジック確認）:"
echo "       source $VENV_DIR/bin/activate"
echo "       cd $SCRIPT_DIR && python main.py --test"
echo ""
echo "  3. カメラプロセスを起動:"
echo "       sudo systemctl start $SERVICE_NAME"
echo ""
echo "  4. ログをリアルタイムで確認:"
echo "       journalctl -u $SERVICE_NAME -f"
echo ""
echo "  5. 再起動後も自動で起動するか確認:"
echo "       sudo reboot"
echo "       # 起動後: sudo systemctl status $SERVICE_NAME"
echo ""
echo "------------------------------------------------------------"
echo " Laravel スケジューラ（同じ Pi で Laravel を動かす場合）"
echo "------------------------------------------------------------"
echo "  cd $(dirname "$SCRIPT_DIR")/app && bash setup_scheduler.sh"
echo "============================================================"
