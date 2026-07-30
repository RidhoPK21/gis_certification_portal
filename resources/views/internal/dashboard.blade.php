@extends('layouts.app')

@section('title', 'Dashboard Internal')

@section('content')
    <div class="page-head">
        <div>
            <h1>Ruang kerja {{ auth()->user()->roles->pluck('name')->join(', ') }}</h1>
            <p>Antrean menampilkan order yang relevan dengan tanggung jawab role Anda.</p>
        </div>
    </div>

    <div class="stat-grid">
        <div class="stat-card"><small>Seluruh Order</small><strong>{{ $stats['all'] }}</strong></div>
        <div class="stat-card"><small>Antrean Role</small><strong>{{ $stats['queue'] }}</strong></div>
        <div class="stat-card"><small>Melewati SLA</small><strong>{{ $stats['overdue'] }}</strong></div>
        <div class="stat-card"><small>Selesai</small><strong>{{ $stats['completed'] }}</strong></div>
    </div>

    <section class="card">
        <div class="flex justify-between items-center mb-2">
            <h2 class="mb-0">Antrean terbaru</h2>
            <form method="get" class="mb-0">
                <select class="form-select btn-sm" name="scheme_id" onchange="this.form.submit()" style="padding:4px 24px 4px 10px;font-size:12px">
                    <option value="">Semua Skema</option>
                    @foreach ($schemes as $scheme)
                        <option value="{{ $scheme->id }}" @selected((int) request('scheme_id') === $scheme->id)>{{ $scheme->short_name }}</option>
                    @endforeach
                </select>
            </form>
        </div>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Order</th>
                        <th>Klien</th>
                        <th>Skema</th>
                        <th>Status</th>
                        <th>Diperbarui</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($applications as $app)
                        <tr>
                            <td style="border-left: 4px solid {{ $app->scheme->accent_color }};">
                                <strong>{{ $app->order_number ?: 'Draft #' . $app->id }}</strong>
                            </td>
                            <td>{{ $app->company_name }}<div class="small muted">{{ $app->client->name }}</div></td>
                            <td><x-scheme-badge :scheme="$app->scheme" /></td>
                            <td>
                                <span class="badge badge-{{ \App\Enums\ApplicationStatus::tryFrom($app->status)?->tone() ?? 'neutral' }}">
                                    @statuslabel($app->status)
                                </span>
                            </td>
                            <td>{{ $app->updated_at->diffForHumans() }}</td>
                            <td>
                                @if (auth()->user()->hasRole(['admin_application', 'superadmin']))
                                    <a class="btn btn-light btn-sm" href="{{ route('internal.applications.show', $app) }}">Buka</a>
                                @elseif (auth()->user()->hasRole('finance') && Route::has('finance.show'))
                                    <a class="btn btn-light btn-sm" href="{{ route('finance.show', $app) }}">Buka</a>
                                @elseif (auth()->user()->hasRole('auditor') && Route::has('audit.show'))
                                    <a class="btn btn-light btn-sm" href="{{ route('audit.show', $app) }}">Buka</a>
                                @elseif (auth()->user()->hasRole('technical') && Route::has('technical.show'))
                                    <a class="btn btn-light btn-sm" href="{{ route('technical.show', $app) }}">Buka</a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="empty">Tidak ada antrean pada role ini.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
