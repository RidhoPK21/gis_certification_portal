@extends('layouts.app')

@section('title', 'Finance ' . $application->order_number)

@section('content')
    <div class="page-head">
        <div>
            <h1>{{ $application->order_number }}</h1>
            <p>{{ $application->company_name }} · {{ $application->scheme->short_name }}</p>
        </div>
        <span class="badge badge-info">@statuslabel($application->status)</span>
    </div>

    <div class="grid-2">
        <section class="card">
            <h2>Invoice</h2>
            <form method="post" action="{{ route('finance.invoice', $application) }}" enctype="multipart/form-data" data-ajax>
                @csrf
                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label">Nomor Invoice <span class="required">*</span></label>
                        <input class="form-control" name="invoice_number" value="{{ old('invoice_number', $application->invoice?->invoice_number) }}" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Nilai Invoice (IDR) <span class="required">*</span></label>
                        <input class="form-control" type="number" step="0.01" name="amount" value="{{ old('amount', $application->invoice?->amount) }}" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Tanggal Invoice</label>
                        <input class="form-control" type="date" name="invoice_date" value="{{ old('invoice_date', optional($application->invoice?->invoice_date)->format('Y-m-d') ?: now()->format('Y-m-d')) }}" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Status Pembayaran <span class="required">*</span></label>
                        <select class="form-select" name="payment_stage" required>
                            @foreach (\App\Models\Invoice::STAGES as $val => $label)
                                <option value="{{ $val }}" @selected(old('payment_stage', $application->invoice?->payment_stage) === $val)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">File Invoice</label>
                    <input class="form-control" type="file" name="invoice_file">
                </div>
                <div class="form-group">
                    <label class="form-label">Catatan Finance</label>
                    <textarea class="form-textarea" name="notes">{{ old('notes', $application->invoice?->notes) }}</textarea>
                </div>
                <button class="btn btn-primary">Simpan / Terbitkan Invoice</button>
            </form>
        </section>
        <section class="card">
            <h2>Ringkasan Pembayaran</h2>
            @if ($application->invoice)
                <dl class="detail-list">
                    <dt>Status (Finance)</dt><dd>{{ $application->invoice->stageLabel() }}</dd>
                    <dt>Milestone Saat Ini</dt><dd>Tahap {{ $application->invoice->current_milestone }}</dd>
                    <dt>Total Invoice</dt><dd>Rp {{ number_format((float) $application->invoice->amount, 0, ',', '.') }}</dd>
                    <dt>Total Terverifikasi</dt><dd>Rp {{ number_format((float) $application->invoice->payments->where('status', 'verified')->sum('amount'), 0, ',', '.') }}</dd>
                    @if ($application->invoice->file_path)
                        <dt>File Invoice</dt>
                        <dd><a class="btn btn-light btn-sm" href="{{ route('secure-files.invoice', $application->invoice) }}">Download Invoice</a></dd>
                    @endif
                </dl>
            @else
                <div class="empty">Buat invoice terlebih dahulu.</div>
            @endif
        </section>
    </div>

    @if ($application->invoice)
        <section class="card mt-2">
            <h2>Catat Pembayaran</h2>
            <form method="post" action="{{ route('finance.payment', $application) }}" enctype="multipart/form-data" data-ajax>
                @csrf
                <div class="grid-3">
                    <div class="form-group">
                        <label class="form-label">Milestone</label>
                        <select class="form-select" name="milestone" required>
                            <option value="1">Tahap 1</option>
                            <option value="2">Tahap 2</option>
                            <option value="3">Tahap 3</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Jumlah</label>
                        <input class="form-control" type="number" step="0.01" name="amount" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Tanggal Bayar</label>
                        <input class="form-control" type="date" name="payment_date" value="{{ now()->format('Y-m-d') }}" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Verifikasi</label>
                        <select class="form-select" name="status">
                            <option value="verified">Terverifikasi</option>
                            <option value="pending_verification">Menunggu Verifikasi</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Bukti Pembayaran</label>
                        <input class="form-control" type="file" name="proof">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Catatan</label>
                        <input class="form-control" name="notes">
                    </div>
                </div>
                <label><input type="checkbox" name="mark_lunas" value="1"> Tandai invoice sudah lunas setelah transaksi ini</label>
                <div class="mt-2"><button class="btn btn-success">Catat Pembayaran</button></div>
            </form>

            <h3>Riwayat</h3>
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr><th>Tahap</th><th>Jumlah</th><th>Tanggal</th><th>Status</th><th>Catatan</th></tr>
                    </thead>
                    <tbody>
                        @forelse ($application->invoice->payments as $payment)
                            <tr>
                                <td>{{ $payment->milestone }}</td>
                                <td>Rp {{ number_format((float) $payment->amount, 0, ',', '.') }}</td>
                                <td>{{ $payment->payment_date->format('d M Y') }}</td>
                                <td>{{ $payment->status }}</td>
                                <td>{{ $payment->notes ?: '-' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="empty">Belum ada pembayaran.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    @endif
@endsection
