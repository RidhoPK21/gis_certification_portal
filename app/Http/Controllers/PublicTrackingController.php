<?php

namespace App\Http\Controllers;

use App\Enums\ApplicationStatus;
use App\Models\CertificationApplication;
use App\Services\WorkflowService;
use Illuminate\Http\Request;

class PublicTrackingController extends Controller
{
    public function index()
    {
        return view('public.tracking');
    }

    public function track(Request $request, WorkflowService $workflow)
    {
        $data = $request->validate(['order_number' => ['required', 'string', 'max:100']]);
        $application = CertificationApplication::where('order_number', trim($data['order_number']))->first();
        if (! $application) {
            return back()->withErrors(['order_number' => 'Nomor order tidak ditemukan. Periksa kembali penulisannya.'])->withInput();
        }

        return view('public.tracking', [
            'result' => [
                'order_number' => $application->order_number,
                'scheme' => $application->scheme->short_name,
                'status_label' => ApplicationStatus::labelFor($application->status),
                'submitted_at' => optional($application->submitted_at)->format('d M Y'),
                'certificate_available' => in_array($application->status, ['final_certificate', 'completed', 'surveillance'], true),
                'timeline' => $workflow->publicTimeline($application),
            ],
        ]);
    }
}
