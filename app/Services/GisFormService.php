<?php

namespace App\Services;

use App\Models\CertificationApplication;
use App\Models\GisFormRequest;
use App\Models\GisFormTemplate;
use App\Models\SchemeRequiredDocument;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Illuminate\Validation\ValidationException;

/**
 * Alur "Formulir Wajib GIS": template milik LS disimpan superadmin, klien
 * memintanya per permohonan, dan baru setelah permintaan itu disetujui klien
 * dapat mengunduh template sekaligus mengunggah formulir yang sudah diisi.
 */
class GisFormService
{
    public const GROUP = 'gis_form';

    public function __construct(
        private readonly FileStorageService $files,
        private readonly PortalNotificationService $notifications,
        private readonly AuditLogger $audit,
    ) {
    }

    /**
     * Template aktif untuk sebuah skema, terurut sesuai checklist.
     */
    public function templatesForScheme(int $schemeId): Collection
    {
        return GisFormTemplate::where('certification_scheme_id', $schemeId)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('code')
            ->get();
    }

    /**
     * Permintaan terakhir pada sebuah permohonan; null bila klien belum pernah
     * meminta template.
     */
    public function latestRequest(CertificationApplication $application): ?GisFormRequest
    {
        return $application->relationLoaded('gisFormRequests')
            ? $application->gisFormRequests->first()
            : $application->gisFormRequests()->first();
    }

    /**
     * Template sudah dibagikan bila permintaan terakhir berstatus disetujui.
     */
    public function isUnlocked(CertificationApplication $application): bool
    {
        return (bool) $this->latestRequest($application)?->isApproved();
    }

    /**
     * Skema ini memakai Formulir Wajib GIS atau tidak. Skema yang belum
     * dipetakan tetap berjalan seperti sebelumnya tanpa langkah permintaan.
     */
    public function schemeUsesGisForms(int $schemeId): bool
    {
        return SchemeRequiredDocument::where('certification_scheme_id', $schemeId)
            ->where('document_group', self::GROUP)
            ->where('is_active', true)
            ->exists();
    }

    public function requestTemplates(CertificationApplication $application, int $userId, ?string $note = null): GisFormRequest
    {
        if ($this->latestRequest($application)?->isPending()) {
            throw ValidationException::withMessages([
                'gis_form' => 'Permintaan template sebelumnya masih menunggu persetujuan.',
            ]);
        }

        if ($this->isUnlocked($application)) {
            throw ValidationException::withMessages([
                'gis_form' => 'Template Formulir Wajib GIS sudah dibagikan untuk permohonan ini.',
            ]);
        }

        $request = GisFormRequest::create([
            'application_id' => $application->id,
            'requested_by' => $userId,
            'status' => 'pending',
            'client_note' => $note,
        ]);

        $application->unsetRelation('gisFormRequests');

        $this->notifications->sendToRole(
            'admin_application',
            'gis_form_requested',
            'Permintaan Formulir Wajib GIS',
            $application->company_name . ' meminta template Formulir Wajib GIS untuk permohonan '
                . ($application->order_number ?: 'draft #' . $application->id) . '.',
            route('internal.gis-form-requests.index')
        );

        $this->audit->log('gis_form.requested', $request, [], ['application_id' => $application->id]);

        return $request;
    }

    public function approve(GisFormRequest $request, int $userId, ?string $note = null): GisFormRequest
    {
        return $this->respond($request, 'approved', $userId, $note);
    }

    public function reject(GisFormRequest $request, int $userId, string $note): GisFormRequest
    {
        return $this->respond($request, 'rejected', $userId, $note);
    }

    private function respond(GisFormRequest $request, string $status, int $userId, ?string $note): GisFormRequest
    {
        if (! $request->isPending()) {
            throw ValidationException::withMessages([
                'gis_form' => 'Permintaan ini sudah pernah ditanggapi.',
            ]);
        }

        $request->update([
            'status' => $status,
            'response_note' => $note,
            'responded_by' => $userId,
            'responded_at' => now(),
        ]);

        $application = $request->application;

        $this->notifications->send(
            $application->client_id,
            'gis_form_' . $status,
            $status === 'approved' ? 'Template Formulir Wajib GIS tersedia' : 'Permintaan formulir ditolak',
            $status === 'approved'
                ? 'Template Formulir Wajib GIS untuk permohonan ' . ($application->order_number ?: 'draft #' . $application->id)
                    . ' sudah dapat diunduh. Silakan isi dan unggah kembali.'
                : 'Permintaan template Formulir Wajib GIS ditolak. ' . $note,
            route('client.applications.edit', $application)
        );

        $this->audit->log('gis_form.' . $status, $request, [], ['application_id' => $application->id]);

        return $request->refresh();
    }

    /**
     * Menyimpan berkas template baru atau menaikkan versinya bila kode sudah ada.
     */
    public function storeTemplate(int $schemeId, array $attributes, UploadedFile $file, ?int $userId): GisFormTemplate
    {
        $this->files->validate($file);

        return DB::transaction(function () use ($schemeId, $attributes, $file, $userId): GisFormTemplate {
            $existing = GisFormTemplate::where('certification_scheme_id', $schemeId)
                ->where('code', $attributes['code'])
                ->first();

            $version = ($existing?->version ?? 0) + 1;
            $extension = strtolower($file->getClientOriginalExtension());
            $storedName = sprintf(
                '%d_%s_v%d_%s.%s',
                $schemeId,
                Str::slug($attributes['code']),
                $version,
                now()->format('YmdHis'),
                $extension
            );

            $path = $file->storeAs('gis-form-templates/' . $schemeId, $storedName, 'private');

            if (! is_string($path) || $path === '') {
                throw new RuntimeException('Template gagal disimpan ke penyimpanan privat.');
            }

            $checksum = hash_file('sha256', $file->getRealPath());

            if ($checksum === false) {
                Storage::disk('private')->delete($path);

                throw new RuntimeException('Checksum template gagal dibuat.');
            }

            $template = GisFormTemplate::updateOrCreate(
                ['certification_scheme_id' => $schemeId, 'code' => $attributes['code']],
                [
                    'scheme_required_document_id' => $attributes['scheme_required_document_id'] ?? null,
                    'name' => $attributes['name'],
                    'description' => $attributes['description'] ?? null,
                    'version' => $version,
                    'original_name' => $file->getClientOriginalName(),
                    'stored_name' => $storedName,
                    'file_path' => $path,
                    'mime_type' => (string) $file->getMimeType(),
                    'extension' => $extension,
                    'size_bytes' => (int) $file->getSize(),
                    'checksum_sha256' => $checksum,
                    'sort_order' => $attributes['sort_order'] ?? ($existing?->sort_order ?? 0),
                    'is_active' => $attributes['is_active'] ?? true,
                    'uploaded_by' => $userId,
                ]
            );

            /*
             * Berkas versi lama dibuang setelah baris berhasil diperbarui:
             * hanya versi terbaru yang pernah dibagikan ke klien, jadi arsip
             * lama hanya menumpuk di penyimpanan.
             */
            if ($existing && $existing->file_path !== $path) {
                Storage::disk('private')->delete($existing->file_path);
            }

            return $template;
        });
    }
}
