#!/usr/bin/env bash
# ============================================================
#  出席管理システム — 本番デプロイスクリプト
# ============================================================
#  初回セットアップ時と、コードを更新したときに実行する。
#
#  実行方法:
#    cd /path/to/attendance-systemV2/app
#    bash deploy.sh
#
#  前提:
#    - PHP 8.3 / Composer / Node.js / MySQL がインストール済み
#    - .env が設定済み（APP_ENV=production, APP_DEBUG=false など）
#    - nginx + php-fpm が動いている（nginx.example.conf を参照）
# ============================================================
set -e

APP_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

if [ ! -f "$APP_DIR/artisan" ]; then
    echo "エラー: Laravel の app/ ディレクトリ内でこのスクリプトを実行してください。"
    exit 1
fi

cd "$APP_DIR"

echo "============================================================"
echo " 出席管理システム — デプロイ開始"
echo " ディレクトリ: $APP_DIR"
echo "============================================================"
echo ""

# ── .env の本番設定を確認 ──────────────────────────────────────
APP_ENV_VAL=$(grep -E '^APP_ENV=' .env 2>/dev/null | cut -d= -f2 | tr -d '"' || echo "")
APP_DEBUG_VAL=$(grep -E '^APP_DEBUG=' .env 2>/dev/null | cut -d= -f2 | tr -d '"' || echo "")
CAMERA_TOKEN_VAL=$(grep -E '^CAMERA_API_TOKEN=' .env 2>/dev/null | cut -d= -f2 | tr -d '"' || echo "")

WARN=0
if [ "$APP_DEBUG_VAL" = "true" ]; then
    echo "  ⚠️  APP_DEBUG=true のままです。本番では false に変更してください。"
    WARN=1
fi
if [ -z "$CAMERA_TOKEN_VAL" ] || [ "$CAMERA_TOKEN_VAL" = "attendance-camera-secret-token-change-in-production" ]; then
    echo "  ⚠️  CAMERA_API_TOKEN が未設定または初期値のままです。"
    echo "       php -r \"echo bin2hex(random_bytes(32));\" で生成した値を設定してください。"
    WARN=1
fi
if [ $WARN -eq 1 ]; then
    echo ""
    read -r -p "警告があります。それでも続けますか？ [y/N]: " CONFIRM
    [[ "$CONFIRM" =~ ^[Yy]$ ]] || { echo "中断しました。"; exit 1; }
    echo ""
fi

# ── 1. メンテナンスモード ON ──────────────────────────────────
echo "[1/7] メンテナンスモード ON"
php artisan down --render="errors::503" 2>/dev/null || true

# ── 2. 依存パッケージ（本番用: dev 除外）────────────────────
echo "[2/7] Composer パッケージをインストール中..."
composer install --no-dev --optimize-autoloader --no-interaction --quiet
echo "  ✓ 完了"

# ── 3. フロントエンドビルド ────────────────────────────────
echo "[3/7] フロントエンドをビルド中..."
if [ -f "package.json" ]; then
    npm ci --silent
    npm run build --silent
    echo "  ✓ 完了"
else
    echo "  スキップ（package.json なし）"
fi

# ── 4. DB マイグレーション ──────────────────────────────────
echo "[4/7] DB マイグレーションを実行中..."
php artisan migrate --force
echo "  ✓ 完了"

# ── 5. キャッシュ生成 ──────────────────────────────────────
echo "[5/7] キャッシュを生成中..."
php artisan config:cache
php artisan route:cache
php artisan view:cache
echo "  ✓ 完了"

# ── 6. ストレージリンク ────────────────────────────────────
echo "[6/7] ストレージリンクを作成中..."
php artisan storage:link 2>/dev/null || true
echo "  ✓ 完了"

# ── 7. パーミッション修正 ──────────────────────────────────
echo "[7/7] storage/ と bootstrap/cache/ のパーミッションを修正中..."
chmod -R 775 storage bootstrap/cache
# nginx/php-fpm が www-data で動いている場合はグループを合わせる
if getent group www-data > /dev/null 2>&1; then
    sudo chown -R "$USER":www-data storage bootstrap/cache
    sudo usermod -a -G www-data "$USER" 2>/dev/null || true
    echo "  ✓ www-data グループに設定"
else
    echo "  ✓ パーミッション設定（www-data グループなし）"
fi

# ── メンテナンスモード OFF ────────────────────────────────
php artisan up

echo ""
echo "============================================================"
echo " ✓ デプロイ完了"
echo "============================================================"
echo ""
echo " 動作確認:"
echo "   curl http://localhost/up         # Laravel ヘルスチェック"
echo "   sudo systemctl status php8.3-fpm # PHP-FPM の状態"
echo "   sudo systemctl status nginx      # nginx の状態"
echo ""
echo " ログ確認:"
echo "   tail -f $APP_DIR/storage/logs/laravel.log"
echo "   sudo tail -f /var/log/nginx/attendance_error.log"
echo "============================================================"
