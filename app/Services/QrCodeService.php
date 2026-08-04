<?php

namespace App\Services;

use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Encoder\Encoder;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

class QrCodeService
{
    /**
     * Menghasilkan QR sebagai SVG inline.
     *
     * SVG dipilih supaya tidak butuh ekstensi Imagick/GD di server, tetap tajam
     * saat sertifikat dicetak, dan aman disisipkan langsung ke halaman tanpa
     * memanggil aset eksternal.
     */
    public function svg(string $content, int $size = 148): string
    {
        $writer = new Writer(
            new ImageRenderer(
                new RendererStyle($size, 1),
                new SvgImageBackEnd()
            )
        );

        $svg = $writer->writeString($content);

        // Buang deklarasi XML agar SVG valid disisipkan di tengah dokumen HTML.
        return trim(preg_replace('/<\?xml.*?\?>/', '', $svg));
    }

    /**
     * QR sebagai berkas PNG, untuk tombol unduh dan keperluan cetak.
     *
     * Digambar langsung dari matriks encoder memakai GD karena bacon-qr-code
     * hanya menyediakan back-end SVG/EPS/Imagick, sedangkan Imagick tidak
     * tersedia di hosting yang dipakai GIS.
     */
    public function png(string $content, int $size = 720, int $quietZone = 4): string
    {
        $matrix = Encoder::encode($content, ErrorCorrectionLevel::M())->getMatrix();
        $modules = $matrix->getWidth();
        $totalModules = $modules + ($quietZone * 2);

        // Skala dibulatkan ke bawah agar tiap modul tetap kotak utuh: modul
        // pecahan membuat QR buram dan sulit dibaca pemindai.
        $scale = max(1, (int) floor($size / $totalModules));
        $side = $totalModules * $scale;

        $image = imagecreatetruecolor($side, $side);
        $white = imagecolorallocate($image, 255, 255, 255);
        $black = imagecolorallocate($image, 0, 0, 0);
        imagefilledrectangle($image, 0, 0, $side - 1, $side - 1, $white);

        for ($y = 0; $y < $modules; $y++) {
            for ($x = 0; $x < $modules; $x++) {
                if ($matrix->get($x, $y) !== 1) {
                    continue;
                }

                $left = ($x + $quietZone) * $scale;
                $top = ($y + $quietZone) * $scale;
                imagefilledrectangle($image, $left, $top, $left + $scale - 1, $top + $scale - 1, $black);
            }
        }

        ob_start();
        imagepng($image);
        $binary = (string) ob_get_clean();
        imagedestroy($image);

        return $binary;
    }

    /**
     * PNG hanya bisa dibuat bila GD aktif; pemanggil menyiapkan SVG sebagai
     * cadangan supaya tombol unduh tidak pernah gagal total.
     */
    public function supportsPng(): bool
    {
        return function_exists('imagecreatetruecolor') && function_exists('imagepng');
    }

    /**
     * URL halaman verifikasi publik untuk sebuah nomor sertifikat atau order.
     */
    public function verificationUrl(string $number): string
    {
        return route('public.home', ['nomor' => $number]);
    }
}
