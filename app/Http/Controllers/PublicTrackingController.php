<?php

namespace App\Http\Controllers;

use App\Enums\ApplicationStatus;
use App\Models\CertificationApplication;
use App\Services\QrCodeService;
use App\Services\WorkflowService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PublicTrackingController extends Controller
{
    public function index(Request $request, WorkflowService $workflow, QrCodeService $qr)
    {
        /*
         * Parameter ?nomor= dipakai oleh QR verifikasi pada sertifikat.
         * Hasil pencarian langsung ditampilkan agar pemindai QR tidak perlu
         * mengetik ulang apa pun.
         */
        $nomor = trim((string) $request->query('nomor', ''));

        if ($nomor === '') {
            return view('public.tracking');
        }

        $application = $this->findByNumber($nomor);

        if (! $application) {
            return view('public.tracking', ['notFound' => $nomor]);
        }

        return view('public.tracking', [
            'result' => $this->resultFor($application, $workflow, $qr),
        ]);
    }

    public function track(Request $request, WorkflowService $workflow, QrCodeService $qr)
    {
        $data = $request->validate(['order_number' => ['required', 'string', 'max:100']]);
        $application = $this->findByNumber(trim($data['order_number']));

        if (! $application) {
            return back()
                ->withErrors(['order_number' => 'Nomor tidak ditemukan. Periksa kembali penulisannya.'])
                ->withInput();
        }

        return view('public.tracking', [
            'result' => $this->resultFor($application, $workflow, $qr),
        ]);
    }

    /**
     * Mengunduh QR pelacakan sebagai berkas gambar.
     *
     * Nomor tetap dicari lebih dulu supaya endpoint ini tidak berubah menjadi
     * generator QR bebas isi untuk sembarang teks.
     */
    public function qr(Request $request, QrCodeService $qr)
    {
        $nomor = trim((string) $request->query('nomor', ''));
        $application = $nomor === '' ? null : $this->findByNumber($nomor);

        abort_if($application === null, 404);

        $number = $application->order_number ?: $nomor;
        $url = $qr->verificationUrl($number);
        $name = 'QR-Permohonan-'.Str::slug($number);

        if (! $qr->supportsPng()) {
            return response($qr->svg($url, 720), 200, [
                'Content-Type' => 'image/svg+xml',
                'Content-Disposition' => 'attachment; filename="'.$name.'.svg"',
            ]);
        }

        return response($qr->png($url), 200, [
            'Content-Type' => 'image/png',
            'Content-Disposition' => 'attachment; filename="'.$name.'.png"',
        ]);
    }

    /**
     * Menerima nomor order maupun nomor sertifikat. Pemegang sertifikat cetak
     * hanya melihat nomor sertifikat, sedangkan klien mengenal nomor order.
     */
    private function findByNumber(string $number): ?CertificationApplication
    {
        $application = CertificationApplication::where('order_number', $number)->first();

        if ($application) {
            return $application;
        }

        return CertificationApplication::whereHas(
            'certificateFinal',
            fn ($query) => $query->where('certificate_number', $number)
        )->first();
    }

    private function resultFor(CertificationApplication $application, WorkflowService $workflow, QrCodeService $qr): array
    {
        $final = $application->certificateFinal;
        $available = in_array($application->status, ['final_certificate', 'completed', 'surveillance'], true);

        /*
         * QR selalu memakai nomor order supaya satu permohonan hanya punya satu
         * QR, walaupun pencarian tadi dilakukan memakai nomor sertifikat.
         */
        $number = $application->order_number ?: (string) $final?->certificate_number;

        return [
            'order_number' => $application->order_number,
            'qr_number' => $number,
            'qr_svg' => $qr->svg($qr->verificationUrl($number), 190),
            'qr_url' => $qr->verificationUrl($number),
            'qr_download_url' => route('public.qr', ['nomor' => $number]),
            'scheme' => $application->scheme->short_name,
            'status_label' => ApplicationStatus::labelFor($application->status),
            'submitted_at' => optional($application->submitted_at)->format('d M Y'),
            'certificate_available' => $available,
            'certificate_number' => $available ? $final?->certificate_number : null,
            'issued_date' => $available ? optional($final?->issued_date)->format('d M Y') : null,
            'expiry_date' => $available ? optional($final?->expiry_date)->format('d M Y') : null,
            'timeline' => $workflow->publicTimeline($application),
        ];
    }
}
