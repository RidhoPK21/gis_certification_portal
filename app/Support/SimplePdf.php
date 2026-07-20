<?php

namespace App\Support;

/**
 * Small dependency-free PDF writer for fixed-layout text/table documents.
 * It uses core PDF features and JPEG XObjects so it works on shared hosting
 * without requiring a browser renderer or native PDF binary.
 */
class SimplePdf
{
    private const WIDTH = 595.28;
    private const HEIGHT = 841.89;

    private array $pages = [];
    private array $pageImages = [];
    private array $images = [];
    private int $page = -1;
    private float $cursorY = 42;

    public function __construct(private readonly float $margin = 42)
    {
        $this->addPage();
    }

    public function addPage(): void
    {
        $this->pages[] = [];
        $this->pageImages[] = [];
        $this->page = count($this->pages) - 1;
        $this->cursorY = $this->margin;
    }

    public function pageWidth(): float { return self::WIDTH; }
    public function pageHeight(): float { return self::HEIGHT; }
    public function contentWidth(): float { return self::WIDTH - ($this->margin * 2); }
    public function y(): float { return $this->cursorY; }
    public function setY(float $y): void { $this->cursorY = $y; }
    public function moveY(float $amount): void { $this->cursorY += $amount; }

    public function ensureSpace(float $height, ?callable $header = null): void
    {
        if ($this->cursorY + $height > self::HEIGHT - $this->margin) {
            $this->addPage();
            if ($header) {
                $header($this);
            }
        }
    }

    public function text(float $x, float $y, string $text, float $size = 10, bool $bold = false): void
    {
        $font = $bold ? 'F2' : 'F1';
        $safe = $this->escape($this->normalize($text));
        $pdfY = self::HEIGHT - $y;
        $this->command("BT /{$font} {$size} Tf 1 0 0 1 {$x} {$pdfY} Tm ({$safe}) Tj ET");
    }

    public function imageJpeg(string $absolutePath, float $x, float $y, float $width, float $height): void
    {
        if (!is_file($absolutePath)) {
            throw new \RuntimeException('Gambar JPEG untuk PDF tidak ditemukan.');
        }
        $info = @getimagesize($absolutePath);
        if (!$info || ($info['mime'] ?? null) !== 'image/jpeg') {
            throw new \RuntimeException('SimplePdf hanya menerima gambar JPEG yang valid.');
        }

        $hash = hash_file('sha256', $absolutePath);
        $name = null;
        foreach ($this->images as $candidate => $image) {
            if ($image['hash'] === $hash) {
                $name = $candidate;
                break;
            }
        }

        if ($name === null) {
            $name = 'Im'.(count($this->images) + 1);
            $channels = (int) ($info['channels'] ?? 3);
            $colorSpace = match ($channels) {
                1 => '/DeviceGray',
                4 => '/DeviceCMYK',
                default => '/DeviceRGB',
            };
            $this->images[$name] = [
                'hash' => $hash,
                'data' => file_get_contents($absolutePath),
                'width' => (int) $info[0],
                'height' => (int) $info[1],
                'bits' => (int) ($info['bits'] ?? 8),
                'color_space' => $colorSpace,
            ];
        }

        $this->pageImages[$this->page][$name] = true;
        $pdfY = self::HEIGHT - $y - $height;
        $this->command("q {$width} 0 0 {$height} {$x} {$pdfY} cm /{$name} Do Q");
    }

    public function line(float $x1, float $y1, float $x2, float $y2, float $width = 0.5): void
    {
        $py1 = self::HEIGHT - $y1;
        $py2 = self::HEIGHT - $y2;
        $this->command("{$width} w {$x1} {$py1} m {$x2} {$py2} l S");
    }

    public function rect(float $x, float $y, float $w, float $h, float $width = 0.5): void
    {
        $py = self::HEIGHT - $y - $h;
        $this->command("{$width} w {$x} {$py} {$w} {$h} re S");
    }

    public function fillRect(float $x, float $y, float $w, float $h, float $gray = 0.94): void
    {
        $py = self::HEIGHT - $y - $h;
        $this->command("{$gray} g {$x} {$py} {$w} {$h} re f 0 g");
    }

    public function wrappedLines(string $text, float $width, float $fontSize = 9): array
    {
        $text = str_replace(["\r\n", "\r"], "\n", trim($text));
        if ($text === '') {
            return [''];
        }

        $maxChars = max(5, (int) floor($width / max(3.6, $fontSize * 0.53)));
        $lines = [];

        foreach (explode("\n", $text) as $paragraph) {
            $paragraph = trim(preg_replace('/[\t ]+/u', ' ', $paragraph) ?? '');
            if ($paragraph === '') {
                $lines[] = '';
                continue;
            }

            $wrapped = wordwrap($this->normalize($paragraph), $maxChars, "\n", true);
            array_push($lines, ...explode("\n", $wrapped));
        }

        return $lines ?: [''];
    }

    public function cell(float $x, float $y, float $w, float $h, string $text, float $fontSize = 9, bool $bold = false, string $align = 'left', float $padding = 4): void
    {
        $this->rect($x, $y, $w, $h);
        $lines = $this->wrappedLines($text, $w - ($padding * 2), $fontSize);
        $lineHeight = $fontSize * 1.25;
        $total = count($lines) * $lineHeight;
        $startY = $y + max($padding + $fontSize, (($h - $total) / 2) + $fontSize);
        foreach ($lines as $i => $line) {
            $lineWidth = strlen($line) * $fontSize * 0.5;
            $tx = match ($align) {
                'center' => $x + max($padding, ($w - $lineWidth) / 2),
                'right' => $x + $w - $padding - $lineWidth,
                default => $x + $padding,
            };
            $this->text($tx, $startY + ($i * $lineHeight), $line, $fontSize, $bold);
        }
    }

    public function paragraph(string $text, float $fontSize = 9, float $lineHeight = 12, bool $bold = false): void
    {
        $lines = $this->wrappedLines($text, $this->contentWidth(), $fontSize);
        $this->ensureSpace(count($lines) * $lineHeight + 4);
        foreach ($lines as $line) {
            $this->text($this->margin, $this->cursorY + $fontSize, $line, $fontSize, $bold);
            $this->cursorY += $lineHeight;
        }
        $this->cursorY += 4;
    }

    public function raw(): string
    {
        $objects = [];
        $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $fontRegularId = 3;
        $fontBoldId = 4;
        $objects[$fontRegularId] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
        $objects[$fontBoldId] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';

        $nextId = 5;
        $imageIds = [];
        foreach ($this->images as $name => $image) {
            $imageId = $nextId++;
            $imageIds[$name] = $imageId;
            $data = $image['data'];
            $objects[$imageId] = '<< /Type /XObject /Subtype /Image'
                .' /Width '.$image['width'].' /Height '.$image['height']
                .' /ColorSpace '.$image['color_space'].' /BitsPerComponent '.$image['bits']
                .' /Filter /DCTDecode /Length '.strlen($data)." >>\nstream\n{$data}\nendstream";
        }

        $pageIds = [];
        foreach ($this->pages as $pageIndex => $commands) {
            $contentId = $nextId++;
            $pageId = $nextId++;
            $stream = implode("\n", $commands);
            $objects[$contentId] = "<< /Length ".strlen($stream)." >>\nstream\n{$stream}\nendstream";

            $xObjects = [];
            foreach (array_keys($this->pageImages[$pageIndex] ?? []) as $name) {
                $xObjects[] = '/'.$name.' '.$imageIds[$name].' 0 R';
            }
            $xObjectResource = $xObjects ? ' /XObject << '.implode(' ', $xObjects).' >>' : '';
            $objects[$pageId] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 '.self::WIDTH.' '.self::HEIGHT.']'
                .' /Resources << /Font << /F1 '.$fontRegularId.' 0 R /F2 '.$fontBoldId.' 0 R >>'.$xObjectResource.' >>'
                .' /Contents '.$contentId.' 0 R >>';
            $pageIds[] = $pageId;
        }

        $kids = implode(' ', array_map(fn ($id) => "{$id} 0 R", $pageIds));
        $objects[2] = '<< /Type /Pages /Count '.count($pageIds)." /Kids [{$kids}] >>";
        ksort($objects);

        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [0];
        foreach ($objects as $id => $object) {
            $offsets[$id] = strlen($pdf);
            $pdf .= "{$id} 0 obj\n{$object}\nendobj\n";
        }
        $xref = strlen($pdf);
        $maxId = max(array_keys($objects));
        $pdf .= "xref\n0 ".($maxId + 1)."\n0000000000 65535 f \n";
        for ($i = 1; $i <= $maxId; $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$i] ?? 0);
        }
        $pdf .= "trailer\n<< /Size ".($maxId + 1)." /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF";

        return $pdf;
    }

    public function save(string $absolutePath): void
    {
        $directory = dirname($absolutePath);
        if (!is_dir($directory)) {
            mkdir($directory, 0775, true);
        }
        file_put_contents($absolutePath, $this->raw());
    }

    private function command(string $command): void
    {
        $this->pages[$this->page][] = $command;
    }

    private function escape(string $text): string
    {
        return str_replace(['\\', '(', ')', "\r", "\n"], ['\\\\', '\\(', '\\)', ' ', ' '], $text);
    }

    private function normalize(string $text): string
    {
        $text = str_replace(['–', '—', '•', '“', '”', '’', '…'], ['-', '-', '-', '"', '"', "'", '...'], $text);
        $converted = @iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $text);
        return $converted !== false ? $converted : preg_replace('/[^\x20-\x7E]/', '?', $text);
    }
}
