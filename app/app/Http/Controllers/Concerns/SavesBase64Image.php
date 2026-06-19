<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Support\Facades\Storage;

/**
 * カメラから送られてくる base64 画像を検証して public ディスクへ保存する。
 * 申告された拡張子は信用せず、実バイト列から JPEG/PNG のみ受け付ける。
 */
trait SavesBase64Image
{
    /** 撮影画像の最大サイズ（デコード後・バイト） */
    private int $maxImageBytes = 8 * 1024 * 1024;

    /**
     * 不正（非JPEG/PNG・壊れたデータ・サイズ超過）の場合は null を返す。
     *
     * @param  string  $dir  保存先サブディレクトリ（public ディスク基準・末尾スラッシュなし）
     */
    protected function saveBase64Image(string $base64, string $dir): ?string
    {
        // data URI が付いていれば本体だけ取り出す（subtype は信用しない）
        if (preg_match('/^data:image\/[\w.+-]+;base64,/', $base64)) {
            $base64 = substr($base64, strpos($base64, ',') + 1);
        }

        // 厳格デコード（base64 として不正な文字が混じれば false）
        $imageData = base64_decode($base64, true);
        if ($imageData === false || $imageData === '') {
            return null;
        }

        // サイズ上限
        if (strlen($imageData) > $this->maxImageBytes) {
            return null;
        }

        // 実バイト列から画像形式を判定（クライアント申告の拡張子は使わない）
        $info = @getimagesizefromstring($imageData);
        if ($info === false) {
            return null;
        }

        $ext = match ($info[2]) {
            IMAGETYPE_JPEG => 'jpg',
            IMAGETYPE_PNG  => 'png',
            default        => null,   // JPEG / PNG 以外は拒否
        };
        if ($ext === null) {
            return null;
        }

        $filename = uniqid('cap_', true) . '.' . $ext;
        $path     = trim($dir, '/') . '/' . $filename;

        Storage::disk('public')->put($path, $imageData);

        return $path;
    }
}
