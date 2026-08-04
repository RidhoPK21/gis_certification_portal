<?php

namespace App\Http\Controllers;

use App\Enums\ApplicationStatus;
use App\Models\CertificationApplication;
use App\Services\WorkflowService;
use Illuminate\Http\Request;

class PublicTrackingController extends Controller
{
    public function index(Request $request, WorkflowService $workflow)
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
            'result' => $this->resultFor($application, $workflow),
        ]);
    }

    public function track(Request $request, WorkflowService $workflow)
    {
        $data = $request->validate(['order_number' => ['required', 'string', 'max:100']]);
        $application = $this->findByNumber(trim($data['order_number']));

        if (! $application) {
            return back()
                ->withErrors(['order_number' => 'Nomor tidak ditemukan. Periksa kembali penulisannya.'])
                ->withInput();
        }

        return view('public.tracking', [
            'result' => $this->resultFor($application, $workflow),
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

    private function resultFor(CertificationApplication $application, WorkflowService $workflow): array
    {
        $final = $application->certificateFinal;
        $available = in_array($application->status, ['final_certificate', 'completed', 'surveillance'], true);

        return [
            'order_number' => $application->order_number,
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
