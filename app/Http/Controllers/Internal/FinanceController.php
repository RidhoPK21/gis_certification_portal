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

    public function index()
    {
        return view('internal.finance.index', [
            'applications' => CertificationApplication::whereIn('status', self::FINANCE_STATUSES)
                ->with(['scheme', 'client', 'invoice'])
                ->latest()
                ->paginate(20),
        ]);
    }

    public function show(CertificationApplication $application)
    {
        abort_unless(in_array($application->status, self::FINANCE_STATUSES, true), 422, 'Order belum berada pada tahap Finance.');
        $application->load(['scheme', 'client', 'invoice.payments']);

        return view('internal.finance.show', compact('application'));
    }

    public function saveInvoice(Request $request, CertificationApplication $application, FileStorageService $files, AuditLogger $audit, PortalNotificationService $notifications)
    {
        abort_unless(in_array($application->status, self::FINANCE_STATUSES, true), 422, 'Order belum berada pada tahap Finance.');
        $data = $request->validate([
            'invoice_number' => ['required', 'string', 'max:100', Rule::unique('invoices', 'invoice_number')->ignore($application->invoice?->id)],
            'amount' => ['required', 'numeric', 'min:0'],
            'invoice_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:invoice_date'],
            'notes' => ['nullable', 'string'],
            'invoice_file' => ['nullable', 'file'],
        ]);
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
        $audit->log('finance.invoice_saved', $invoice);
        $notifications->send($application->client_id, 'invoice_issued', 'Invoice diterbitkan', 'Invoice '.$invoice->invoice_number.' untuk order '.$application->order_number.' telah tersedia.', route('client.applications.show', $application));

        return back()->with('success', 'Invoice berhasil disimpan.');
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
        } elseif ($status === 'partial' && $application->status === 'invoice_process') {
            $workflow->transition($application, 'payment_partial', 'payment_partial', 'Pembayaran tahap '.$data['milestone'].' tercatat.', $request->user()->id, new \DateTime($data['payment_date']));
        }
        $notifications->send($application->client_id, 'payment_updated', 'Status pembayaran diperbarui', 'Pembayaran order '.$application->order_number.' kini berstatus '.$status.'.', route('client.applications.show', $application));
        $audit->log('finance.payment_recorded', $payment);

        return back()->with('success', 'Pembayaran berhasil dicatat.');
    }
}
