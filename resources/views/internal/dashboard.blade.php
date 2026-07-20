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
        <h2>Antrean terbaru</h2>
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
                            <td><strong>{{ $app->order_number ?: 'Draft #' . $app->id }}</strong></td>
                            <td>{{ $app->company_name }}<div class="small muted">{{ $app->client->name }}</div></td>
                            <td>{{ $app->scheme->short_name }}</td>
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
