<?php

namespace App\Http\Controllers\Internal;

use App\Http\Controllers\Controller;
use App\Models\AuditAssignment;
use App\Models\CertificationApplication;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\PortalNotificationService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Penugasan tim auditor.
 *
 * Dahulu dikerjakan Admin Permohonan di halaman review; sekarang menjadi
 * wewenang Tim Teknis, yang memilih timnya saat tinjauan teknis dan masih boleh
 * menggantinya di halaman Surat Tugas setelah pembayaran lunas. Dipisah dari
 * TechnicalController agar kedua halaman itu memanggil endpoint yang sama.
 */
class AuditAssignmentController extends Controller
{
    public function store(Request $request, CertificationApplication $application, AuditLogger $audit, PortalNotificationService $notifications)
    {
        $data = $request->validate([
            'auditor_id' => ['required', 'integer', 'exists:users,id'],
            'assignment_role' => ['required', Rule::in(['LA', 'A', 'TA'])],
            'stage_code' => ['required', Rule::in(['all', 'stage_1', 'stage_2', 'qms', 'corrective_action'])],
            'assigned_date' => ['required', 'date'],
        ]);

        $auditor = User::whereKey($data['auditor_id'])->where('is_active', true)
            ->whereHas('roles', fn ($query) => $query->where('code', 'auditor'))->firstOrFail();

        $assignment = AuditAssignment::updateOrCreate(
            ['application_id' => $application->id, 'auditor_id' => $auditor->id, 'stage_code' => $data['stage_code']],
            ['assignment_role' => $data['assignment_role'], 'assigned_date' => $data['assigned_date'], 'status' => 'assigned', 'assigned_by' => $request->user()->id]
        );

        $audit->log('audit.assignment_saved', $assignment, [], ['auditor' => $auditor->email]);

        /*
         * Auditor belum boleh mulai bekerja sampai Surat Tugas tahap itu terbit,
         * jadi pemberitahuannya sengaja tidak berbunyi seperti perintah kerja.
         */
        $notifications->send(
            $auditor,
            'auditor_assigned',
            'Pencalonan Tim Audit',
            'Anda dicalonkan sebagai '.$data['assignment_role'].' untuk order '.$application->order_number.'. Surat Tugas akan menyusul dari Tim Teknis.',
            route('audit.show', $application)
        );

        return back()->with('success', 'Auditor berhasil ditugaskan ke order ini.');
    }

    public function destroy(Request $request, AuditAssignment $assignment, AuditLogger $audit)
    {
        $assignment->load(['application', 'auditor']);
        $orderNumber = $assignment->application?->order_number;
        $email = $assignment->auditor?->email;

        $assignment->delete();

        $audit->log('audit.assignment_removed', $assignment, [], ['auditor' => $email, 'order_number' => $orderNumber]);

        return back()->with('success', 'Auditor dikeluarkan dari penugasan order ini.');
    }
}
