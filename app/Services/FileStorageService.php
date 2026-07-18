<?php

namespace App\Services;

use App\Models\ApplicationDocument;
use App\Models\ApplicationDocumentVersion;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FileStorageService
{
    public function validate(UploadedFile $file): void
    {
        if (! $file->isValid()) {
            throw ValidationException::withMessages([
                'file' => 'File gagal diunggah atau file tidak valid.',
            ]);
        }

        $extension = strtolower($file->getClientOriginalExtension());
        $mime = (string) $file->getMimeType();
        $maxMb = (int) config('gis.upload_max_mb', 20);
        $maxBytes = $maxMb * 1024 * 1024;
        $allowedExtensions = (array) config('gis.allowed_extensions', []);
        $allowedMimes = (array) config('gis.allowed_upload_mimes', []);
        $allowedForExtension = (array) config('gis.allowed_upload_pairs.' . $extension, []);

        if ($extension === '' || ! in_array($extension, $allowedExtensions, true)) {
            throw ValidationException::withMessages([
                'file' => 'Ekstensi file tidak diizinkan.',
            ]);
        }

        if ($mime === '' || ! in_array($mime, $allowedMimes, true)) {
            throw ValidationException::withMessages([
                'file' => 'Tipe MIME file tidak diizinkan.',
            ]);
        }

        if (! in_array($mime, $allowedForExtension, true)) {
            throw ValidationException::withMessages([
                'file' => 'Isi file tidak sesuai dengan ekstensi yang digunakan.',
            ]);
        }

        if ((int) $file->getSize() > $maxBytes) {
            throw ValidationException::withMessages([
                'file' => 'Ukuran file melebihi batas ' . $maxMb . ' MB.',
            ]);
        }
    }

    public function storeDocumentVersion(
        ApplicationDocument $document,
        UploadedFile $file,
        int $userId
    ): ApplicationDocumentVersion {
        $this->validate($file);

        return DB::transaction(function () use ($document, $file, $userId): ApplicationDocumentVersion {
            $document->versions()
                ->where('is_current', true)
                ->update(['is_current' => false, 'archived_at' => now()]);

            $version = ((int) $document->versions()->max('version')) + 1;
            $extension = strtolower($file->getClientOriginalExtension());

            $storedName = sprintf(
                '%d_%s_v%d_%s.%s',
                $document->application_id,
                Str::slug($document->document_code),
                $version,
                now()->format('YmdHis'),
                $extension
            );

            $directory = 'applications/' . $document->application_id . '/documents';
            $path = $file->storeAs($directory, $storedName, 'private');

            if (! is_string($path) || $path === '') {
                throw new RuntimeException('File gagal disimpan ke penyimpanan privat.');
            }

            $checksum = hash_file('sha256', $file->getRealPath());

            if ($checksum === false) {
                Storage::disk('private')->delete($path);

                throw new RuntimeException('Checksum file gagal dibuat.');
            }

            return $document->versions()->create([
                'version' => $version,
                'original_name' => $file->getClientOriginalName(),
                'stored_name' => $storedName,
                'file_path' => $path,
                'mime_type' => $file->getMimeType(),
                'extension' => $extension,
                'size_bytes' => (int) $file->getSize(),
                'checksum_sha256' => $checksum,
                'is_current' => true,
                'uploaded_by' => $userId,
            ]);
        });
    }

    public function response(
        string $path,
        string $name,
        string $disposition = 'attachment'
    ): StreamedResponse {
        $disk = Storage::disk('private');
        $safePath = $this->sanitizePath($path);

        abort_unless($disk->exists($safePath), 404, 'File tidak ditemukan.');

        $safeName = $this->sanitizeDownloadName($name !== '' ? $name : basename($safePath));

        $safeDisposition = in_array($disposition, ['attachment', 'inline'], true)
            ? $disposition
            : 'attachment';

        return $disk->response(
            $safePath,
            $safeName,
            [
                'X-Content-Type-Options' => 'nosniff',
                'Cache-Control' => 'private, no-store, no-cache, must-revalidate, max-age=0',
                'Pragma' => 'no-cache',
                'Expires' => '0',
            ],
            $safeDisposition
        );
    }

    private function sanitizePath(string $path): string
    {
        $path = str_replace('\\', '/', trim($path));
        $path = ltrim($path, '/');
        $containsTraversal = preg_match('#(^|/)\.\.(/|$)#', $path) === 1;

        abort_if(
            $path === '' || str_contains($path, "\0") || $containsTraversal,
            400,
            'Path file tidak valid.'
        );

        return $path;
    }

    private function sanitizeDownloadName(string $name): string
    {
        $name = str_replace('\\', '/', trim($name));
        $name = basename($name);
        $name = Str::ascii($name);
        $name = preg_replace('/[\x00-\x1F\x7F]+/', '_', $name) ?? '';
        $name = preg_replace('/[^A-Za-z0-9._() -]+/', '_', $name) ?? '';
        $name = preg_replace('/[\s_-]{2,}/', '_', $name) ?? '';
        $name = trim($name, " ._-");

        return $name !== '' ? $name : 'document';
    }
}
