<?php

namespace App\Http\Controllers;

use App\Models\CertificateDownloadLog;
use App\Services\CertificateLinkService;
use App\Services\FileStorageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class CertificateShareController extends Controller
{
    public function previewDraft(string $token, CertificateLinkService $links)
    {
        $link = $links->find($token);
        abort_unless($link->link_type === 'draft' && $link->isUsable(), 410, 'Link preview sudah tidak aktif atau kedaluwarsa.');
        $link->load(['application', 'draft']);
        $links->log($link, 'preview_page');

        return view('certificates.draft-preview', ['link' => $link, 'token' => $token, 'application' => $link->application]);
    }

    public function streamDraft(string $token, CertificateLinkService $links, FileStorageService $files)
    {
        $link = $links->find($token);
        abort_unless($link->link_type === 'draft' && $link->isUsable(), 410);
        $link->load('draft');
        $links->log($link, 'preview_stream');

        return $files->response($link->draft->file_path, 'draft-sertifikat.pdf', 'inline');
    }

    public function finalAccess(string $token, CertificateLinkService $links)
    {
        $link = $links->find($token);
        abort_unless($link->link_type === 'final' && $link->isUsable(), 410, 'Link sertifikat final sudah tidak aktif atau kedaluwarsa.');
        $link->load(['application', 'final']);

        return view('certificates.final-access', ['link' => $link, 'token' => $token, 'application' => $link->application]);
    }

    public function downloadFinal(Request $request, string $token, CertificateLinkService $links, FileStorageService $files)
    {
        $link = $links->find($token);
        abort_unless($link->link_type === 'final' && $link->isUsable(), 410);
        $data = $request->validate(['password' => ['required', 'string']]);
        if (! $link->password_hash || ! Hash::check($data['password'], $link->password_hash)) {
            $links->log($link, 'download', false, 'invalid_password');

            return back()->withErrors(['password' => 'Password sertifikat tidak benar.']);
        }
        $link->load('final');
        $links->log($link, 'download', true);
        CertificateDownloadLog::create([
            'certificate_final_id' => $link->certificate_final_id, 'certificate_share_link_id' => $link->id,
            'user_id' => auth()->id(), 'ip_address' => $request->ip(), 'user_agent' => $request->userAgent(), 'downloaded_at' => now(),
        ]);

        return $files->response($link->final->file_path, $link->final->original_name, 'attachment');
    }
}
