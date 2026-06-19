#!/usr/bin/env bash
# ============================================================================
#  Laravel スケジューラ自動実行セットアップ
# ----------------------------------------------------------------------------
#  このスクリプトは「cron（クーロン）」に 1行だけ登録します。
#
#    毎分 → php artisan schedule:run を実行
#
#  Laravel 側ではこの schedule:run が、routes/console.php に書かれた
#  以下の定期処理を “登録した時刻になったら” 自動で走らせます。
#
#    ・sessions:generate … スケジュールから出席セッションを毎日生成（0:05）
#    ・sessions:finalize … 予定終了を過ぎたセッションを締めて欠席を記録（5分毎）
#
#  ※ cron 自体は毎分動きますが、実際に各コマンドが実行されるのは
#    Laravel が指定した時刻だけなので無駄な負荷はかかりません。
#
#  使い方:
#    cd /path/to/attendance-systemV2/app
#    bash setup_scheduler.sh          # 登録する
#    bash setup_scheduler.sh --remove # 登録を解除する
# ============================================================================
set -e

# --- このスクリプトが置かれているディレクトリ（= Laravel の app ディレクトリ）---
APP_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

# artisan が無ければ場所が間違っている
if [ ! -f "$APP_DIR/artisan" ]; then
    echo "エラー: $APP_DIR に artisan が見つかりません。"
    echo "       Laravel の app ディレクトリ内でこのスクリプトを実行してください。"
    exit 1
fi

# --- php の実行ファイルパスを自動検出 ---
PHP_BIN="$(command -v php || true)"
if [ -z "$PHP_BIN" ]; then
    echo "エラー: php が見つかりません。先に PHP をインストールしてください。"
    exit 1
fi

# --- ログ出力先（cron は画面が無いのでファイルに残す）---
LOG_FILE="$APP_DIR/storage/logs/scheduler.log"

# --- cron に書き込む 1行 ---
CRON_LINE="* * * * * cd $APP_DIR && $PHP_BIN artisan schedule:run >> $LOG_FILE 2>&1"

# --- 目印コメント（あとで見つけやすく／重複登録を防ぐため）---
MARKER="# attendance-system scheduler ($APP_DIR)"

# ----------------------------------------------------------------------------
# 既存の登録を取り除く（このアプリの分だけ。他の cron はそのまま残す）
# ----------------------------------------------------------------------------
CURRENT="$(crontab -l 2>/dev/null || true)"
CLEANED="$(printf '%s\n' "$CURRENT" | grep -vF "$MARKER" | grep -vF "$APP_DIR && $PHP_BIN artisan schedule:run" || true)"

if [ "${1:-}" = "--remove" ]; then
    printf '%s\n' "$CLEANED" | crontab -
    echo "✓ スケジューラの cron 登録を解除しました。"
    exit 0
fi

# ----------------------------------------------------------------------------
# 登録（目印コメント + 実行行を追記）
# ----------------------------------------------------------------------------
{
    printf '%s\n' "$CLEANED"
    printf '%s\n' "$MARKER"
    printf '%s\n' "$CRON_LINE"
} | sed '/^$/d' | crontab -

echo "============================================================"
echo " ✓ Laravel スケジューラを cron に登録しました"
echo "============================================================"
echo " アプリ : $APP_DIR"
echo " php    : $PHP_BIN"
echo " ログ   : $LOG_FILE"
echo ""
echo " 登録内容（crontab -l で確認できます）:"
echo "   $CRON_LINE"
echo ""
echo " 解除したいとき: bash setup_scheduler.sh --remove"
echo "============================================================"
