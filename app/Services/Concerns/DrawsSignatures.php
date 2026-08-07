<?php

namespace App\Services\Concerns;

use App\Support\SimplePdf;
use Illuminate\Support\Facades\Storage;

/**
 * Menempelkan gambar tanda tangan ke dalam PDF.
 *
 * Dipakai bersama oleh tiga pencetak formulir. Tanda tangan peninjau internal
 * berasal dari unggahan profil (JPEG), sedangkan tanda tangan pemohon boleh
 * PNG — sementara SimplePdf hanya menerima JPEG. Karena itu berkas non-JPEG
 * dikonversi lebih dulu ke berkas sementara.
 */
trait DrawsSignatures
{
    /**
     * Gagal diam-diam: kotak tanda tangan tetap tercetak dengan nama ketik,
     * dan pembuatan PDF tidak boleh terganggu hanya karena satu gambar.
     */
    protected function drawSignature(SimplePdf $pdf, ?string $relativePath, float $x, float $y, float $maxW, float $maxH): void
    {
        if (! $relativePath) {
            return;
        }

        $absolute = Storage::disk('private')->path($relativePath);
        if (! is_file($absolute)) {
            return;
        }

        $info = @getimagesize($absolute);
        if (! $info || (int) $info[0] <= 0 || (int) $info[1] <= 0) {
            return;
        }

        $temporary = null;
        if (($info[2] ?? null) !== IMAGETYPE_JPEG) {
            $temporary = $this->convertToJpeg($absolute, $info[2] ?? null);
            if (! $temporary) {
                return;
            }
            $absolute = $temporary;
        }

        $scale = min($maxW / (int) $info[0], $maxH / (int) $info[1]);

        try {
            $pdf->imageJpeg($absolute, $x, $y, (int) $info[0] * $scale, (int) $info[1] * $scale);
        } catch (\Throwable $e) {
            // abaikan
        } finally {
            if ($temporary && is_file($temporary)) {
                @unlink($temporary);
            }
        }
    }

    private function convertToJpeg(string $absolute, ?int $type): ?string
    {
        if (! function_exists('imagecreatefrompng')) {
            return null;
        }

        $image = match ($type) {
            IMAGETYPE_PNG => @imagecreatefrompng($absolute),
            IMAGETYPE_GIF => @imagecreatefromgif($absolute),
            IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($absolute) : false,
            default => false,
        };

        if (! $image) {
            return null;
        }

        // Latar putih supaya PNG transparan tidak menjadi hitam saat jadi JPEG.
        $canvas = imagecreatetruecolor(imagesx($image), imagesy($image));
        imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255));
        imagecopy($canvas, $image, 0, 0, 0, 0, imagesx($image), imagesy($image));

        $target = tempnam(sys_get_temp_dir(), 'sig').'.jpg';

        return imagejpeg($canvas, $target, 92) ? $target : null;
    }
}
