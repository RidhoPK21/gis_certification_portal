<?php

namespace App\Services;

use App\Models\SniProductMaster;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use ZipArchive;

class SniProductImportService
{
    private const HEADERS = [
        'kode_produk' => 'product_code', 'product_code' => 'product_code', 'kode' => 'product_code',
        'nama_produk' => 'product_name', 'product_name' => 'product_name', 'produk' => 'product_name',
        'kategori' => 'category', 'category' => 'category',
        'nomor_sni' => 'sni_number', 'sni_number' => 'sni_number', 'sni' => 'sni_number',
        'sistem_sertifikasi' => 'certification_system', 'certification_system' => 'certification_system',
        'status_aktif' => 'is_active', 'is_active' => 'is_active', 'aktif' => 'is_active',
        'dokumen_tambahan' => 'document_rules', 'document_rules' => 'document_rules',
        'catatan' => 'notes', 'notes' => 'notes',
    ];

    public function import(UploadedFile $file): array
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $rows = match ($extension) {
            'csv', 'txt' => $this->csv($file->getRealPath()),
            'xlsx' => $this->xlsx($file->getRealPath()),
            default => throw ValidationException::withMessages(['file' => 'Format import harus CSV atau XLSX.']),
        };
        if (count($rows) < 2) {
            throw ValidationException::withMessages(['file' => 'File import tidak memiliki baris data.']);
        }

        $headers = array_map(fn ($value) => self::HEADERS[$this->normalize((string) $value)] ?? null, array_shift($rows));
        if (! in_array('product_code', $headers, true) || ! in_array('product_name', $headers, true)) {
            throw ValidationException::withMessages(['file' => 'Header wajib: kode_produk dan nama_produk.']);
        }

        $created = $updated = $skipped = 0;
        foreach ($rows as $row) {
            $data = [];
            foreach ($headers as $index => $key) {
                if ($key) {
                    $data[$key] = trim((string) ($row[$index] ?? ''));
                }
            }
            if (($data['product_code'] ?? '') === '' || ($data['product_name'] ?? '') === '') {
                $skipped++;
                continue;
            }
            $existing = SniProductMaster::where('product_code', $data['product_code'])->first();
            $metadata = array_filter(['document_rules' => $data['document_rules'] ?? null, 'notes' => $data['notes'] ?? null]);
            SniProductMaster::updateOrCreate(['product_code' => $data['product_code']], [
                'product_name' => $data['product_name'], 'category' => ($data['category'] ?? '') ?: null,
                'sni_number' => ($data['sni_number'] ?? '') ?: null,
                'certification_system' => ($data['certification_system'] ?? '') ?: 'System 5',
                'metadata' => $metadata ?: null,
                'is_active' => $this->boolean($data['is_active'] ?? '1'),
            ]);
            $existing ? $updated++ : $created++;
        }

        return compact('created', 'updated', 'skipped');
    }

    private function csv(string $path): array
    {
        $handle = fopen($path, 'rb');
        if (! $handle) {
            throw ValidationException::withMessages(['file' => 'File CSV tidak dapat dibaca.']);
        }
        $rows = [];
        $delimiter = ',';
        $first = fgets($handle);
        rewind($handle);
        if ($first !== false && substr_count($first, ';') > substr_count($first, ',')) {
            $delimiter = ';';
        }
        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            $rows[] = $row;
        }
        fclose($handle);

        return $rows;
    }

    private function xlsx(string $path): array
    {
        if (! class_exists(ZipArchive::class) || ! function_exists('simplexml_load_string')) {
            throw ValidationException::withMessages(['file' => 'Import XLSX memerlukan ekstensi PHP zip dan SimpleXML. Gunakan CSV atau aktifkan ekstensi tersebut.']);
        }
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            throw ValidationException::withMessages(['file' => 'File XLSX tidak valid.']);
        }
        $shared = [];
        if (($xml = $zip->getFromName('xl/sharedStrings.xml')) !== false) {
            $doc = simplexml_load_string($xml);
            if ($doc) {
                foreach ($doc->si as $si) {
                    $shared[] = $this->sharedText($si);
                }
            }
        }
        $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();
        if ($sheet === false) {
            throw ValidationException::withMessages(['file' => 'Worksheet pertama tidak ditemukan.']);
        }
        $xml = simplexml_load_string($sheet);
        if (! $xml) {
            throw ValidationException::withMessages(['file' => 'Worksheet XLSX tidak dapat dibaca.']);
        }
        $xml->registerXPathNamespace('x', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $rows = [];
        foreach ($xml->xpath('//x:sheetData/x:row') ?: [] as $row) {
            $out = [];
            foreach ($row->c as $cell) {
                $ref = (string) $cell['r'];
                preg_match('/^[A-Z]+/', $ref, $m);
                $index = $this->columnIndex($m[0] ?? 'A');
                $type = (string) $cell['t'];
                $value = (string) $cell->v;
                if ($type === 's') {
                    $value = $shared[(int) $value] ?? '';
                } elseif ($type === 'inlineStr') {
                    $value = (string) $cell->is->t;
                }
                $out[$index] = $value;
            }
            if ($out) {
                ksort($out);
                $max = max(array_keys($out));
                $rows[] = array_map(fn ($i) => $out[$i] ?? '', range(0, $max));
            }
        }

        return $rows;
    }

    private function sharedText($node): string
    {
        if (isset($node->t)) {
            return (string) $node->t;
        }
        $text = '';
        foreach ($node->r ?? [] as $run) {
            $text .= (string) $run->t;
        }

        return $text;
    }

    private function columnIndex(string $letters): int
    {
        $n = 0;
        foreach (str_split($letters) as $c) {
            $n = $n * 26 + (ord($c) - 64);
        }

        return $n - 1;
    }

    private function normalize(string $value): string
    {
        return Str::of($value)->lower()->ascii()->replaceMatches('/[^a-z0-9]+/', '_')->trim('_')->toString();
    }

    private function boolean(string $value): bool
    {
        return in_array(strtolower(trim($value)), ['1', 'true', 'ya', 'yes', 'aktif', 'active'], true);
    }
}
