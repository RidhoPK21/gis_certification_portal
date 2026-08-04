<?php

namespace App\Services;

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
     * URL halaman verifikasi publik untuk sebuah nomor sertifikat atau order.
     */
    public function verificationUrl(string $number): string
    {
        return route('public.home', ['nomor' => $number]);
    }
}
