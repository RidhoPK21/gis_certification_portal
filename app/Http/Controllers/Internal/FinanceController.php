<?php

namespace App\Http\Controllers\Internal;

use App\Http\Controllers\Controller;
use App\Models\CertificationApplication;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Services\AuditLogger;
use App\Services\FileStorageService;
use App\Services\PortalNotificationService;
use App\Services\WorkflowService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class FinanceController extends Controller
{
    private const FINANCE_STATUSES = ['invoice_process', 'payment_partial', 'payment_completed'];

    public function index(Request $request)
    {
        $query = CertificationApplication::whereIn('status', self::FINANCE_STATUSES)
            ->with(['scheme', 'client', 'invoice']);

        if ($request->filled('q')) {
            $q = trim((string) $request->string('q'));
            $query->where(function ($sub) use ($q) {
                $sub->where('order_number', 'like', "%{$q}%")
                    ->orWhere('company_name', 'like', "%{$q}%");
            });
        }

        if ($request->filled('scheme_id')) {
            $query->where('certification_scheme_id', $request->integer('scheme_id'));
        }

        return view('internal.finance.index', [
            'applications' => $query->latest()->paginate(20)->withQueryString(),
            'schemes' => \App\Models\CertificationScheme::orderBy('sort_order')->get(),
        ]);
    }

    public function show(CertificationApplication $application)
    {
        abort_unless(in_array($application->status, self::FINANCE_STATUSES, true), 422, 'Order belum berada pada tahap Finance.');
        $application->load(['scheme', 'client', 'invoice.payments']);

        return view('internal.finance.show', compact('application'));
    }

    public function saveInvoice(Request $request, CertificationApplication $application, WorkflowService $workflow, FileStorageService $files, AuditLogger $audit, PortalNotificationService $notifications)
    {
        abort_unless(in_array($application->status, self::FINANCE_STATUSES, true), 422, 'Order belum berada pada tahap Finance.');
        $data = $request->validate([
            'invoice_number' => ['required', 'string', 'max:100', Rule::unique('invoices', 'invoice_number')->ignore($application->invoice?->id)],
            'amount' => ['nullable', 'numeric', 'min:0'],
            'invoice_date' => ['required', 'date'],
            'payment_stage' => ['required', Rule::in(array_keys(Invoice::STAGES))],
            'notes' => ['nullable', 'string'],
            'invoice_file' => ['nullable', 'file'],
        ]);
        $data['amount'] = $data['amount'] ?? ($application->invoice?->amount ?? 0);
        unset($data['invoice_file']);
        $path = $application->invoice?->file_path;
        if ($request->hasFile('invoice_file')) {
            $files->validate($request->file('invoice_file'));
            $name = $application->id.'_invoice_'.Str::random(8).'.'.strtolower($request->file('invoice_file')->getClientOriginalExtension());
            $path = $request->file('invoice_file')->storeAs('applications/'.$application->id.'/finance', $name, 'private');
        }
        $invoice = Invoice::updateOrCreate(
            ['application_id' => $application->id],
            $data + ['payment_status' => $application->invoice?->payment_status ?? 'unpaid', 'file_path' => $path, 'created_by' => $request->user()->id]
        );
        $this->applyStageWorkflow($application, $invoice, $data['payment_stage'], $workflow, $request);
        $audit->log('finance.invoice_saved', $invoice);
        $notifications->send($application->client_id, 'invoice_issued', 'Invoice diterbitkan', 'Invoice '.$invoice->invoice_number.' untuk order '.$application->order_number.' telah tersedia.', route('client.applications.show', $application));

        return $this->savedResponse($request, 'Invoice berhasil disimpan.');
    }

    private function applyStageWorkflow(CertificationApplication $application, Invoice $invoice, string $stage, WorkflowService $workflow, Request $request): void
    {
        $target = match ($stage) {
            'tahap_1', 'tahap_2', 'tahap_3' => 'payment_partial',
            'lunas' => 'payment_completed',
            default => null,
        };

        if (in_array($stage, ['tahap_1', 'tahap_2', 'tahap_3'], true)) {
            $milestone = (int) substr($stage, -1);
            $invoice->update(['current_milestone' => max($invoice->current_milestone, $milestone)]);
        }

        if ($target && $workflow->allows($application->status, $target)) {
            $workflow->transition($application, $target, $target, 'Status pembayaran diperbarui ke '.Invoice::STAGES[$stage].'.', $request->user()->id);
        }
    }

    public function addPayment(Request $request, CertificationApplication $application, WorkflowService $workflow, FileStorageService $files, PortalNotificationService $notifications, AuditLogger $audit)
    {
        abort_unless(in_array($application->status, self::FINANCE_STATUSES, true), 422, 'Order belum berada pada tahap Finance.');
        $invoice = $application->invoice;
        abort_unless($invoice, 422, 'Buat invoice terlebih dahulu.');
        $data = $request->validate([
            'milestone' => ['required', 'integer', 'between:1,3'],
            'amount' => ['required', 'numeric', 'min:0'],
            'payment_date' => ['required', 'date'],
            'status' => ['required', Rule::in(['verified', 'pending_verification'])],
            'notes' => ['nullable', 'string'],
            'proof' => ['nullable', 'file'],
            'mark_lunas' => ['nullable', 'boolean'],
        ]);
        $path = null;
        if ($request->hasFile('proof')) {
            $files->validate($request->file('proof'));
            $name = $application->id.'_payment_'.$data['milestone'].'_'.Str::random(8).'.'.strtolower($request->file('proof')->getClientOriginalExtension());
            $path = $request->file('proof')->storeAs('applications/'.$application->id.'/finance', $name, 'private');
        }
        [$payment, $status] = DB::transaction(function () use ($invoice, $data, $path, $request) {
            $p = InvoicePayment::create([
                'invoice_id' => $invoice->id, 'milestone' => $data['milestone'], 'amount' => $data['amount'],
                'payment_date' => $data['payment_date'], 'status' => $data['status'], 'proof_path' => $path,
                'notes' => $data['notes'] ?? null, 'recorded_by' => $request->user()->id,
            ]);
            $paid = (float) $invoice->payments()->where('status', 'verified')->sum('amount');
            $newStatus = ($request->boolean('mark_lunas') || $paid >= (float) $invoice->amount) ? 'paid' : 'partial';
            $old = $invoice->payment_status;
            $invoice->update(['payment_status' => $newStatus, 'current_milestone' => max($invoice->current_milestone, $data['milestone'])]);
            DB::table('payment_status_history')->insert([
                'invoice_id' => $invoice->id, 'from_status' => $old, 'to_status' => $newStatus, 'milestone' => $data['milestone'],
                'action_date' => $data['payment_date'], 'notes' => $data['notes'] ?? null, 'performed_by' => $request->user()->id,
                'created_at' => now(), 'updated_at' => now(),
            ]);

            return [$p, $newStatus];
        });
        if ($status === 'paid' && in_array($application->status, ['invoice_process', 'payment_partial'], true)) {
            $workflow->transition($application, 'payment_completed', 'payment_completed', 'Pembayaran dinyatakan lunas.', $request->user()->id, new \DateTime($data['payment_date']));
            $notifications->sendToRole('admin_application', 'payment_completed', 'Pembayaran Selesai', 'Pembayaran order '.$application->order_number.' telah lunas. Audit siap dijadwalkan.', route('internal.applications.show', $application));
        } elseif ($status === 'partial' && $application->status === 'invoice_process') {
            $workflow->transition($application, 'payment_partial', 'payment_partial', 'Pembayaran tahap '.$data['milestone'].' tercatat.', $request->user()->id, new \DateTime($data['payment_date']));
        }
        $notifications->send($application->client_id, 'payment_updated', 'Status pembayaran diperbarui', 'Pembayaran order '.$application->order_number.' kini berstatus '.$status.'.', route('client.applications.show', $application));
        $audit->log('finance.payment_recorded', $payment);

        return $this->savedResponse($request, 'Pembayaran berhasil dicatat.');
    }
}
