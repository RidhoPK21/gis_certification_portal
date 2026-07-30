@extends('layouts.app')

@section('title', 'Panduan Penggunaan')

@push('styles')
<style>
    .panduan-container {
        display: flex;
        flex-direction: column;
        gap: 28px;
    }

    .role-switcher-card {
        padding: 16px 22px;
        border: 1px solid var(--border);
        border-radius: 14px;
        background: var(--surface);
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 14px;
        box-shadow: 0 4px 14px var(--shadow);
    }

    .role-switcher-label {
        font-size: 13px;
        color: var(--muted);
        font-weight: 600;
        margin: 0;
    }

    .role-switcher-buttons {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
    }

    .role-switch-btn {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 8px 15px;
        border-radius: 999px;
        border: 1px solid var(--border);
        background: var(--surface-alt);
        color: var(--text);
        font-size: 12px;
        font-weight: 600;
        text-decoration: none;
        transition: all 0.2s ease;
    }

    .role-switch-btn:hover {
        border-color: #3b82f6;
        background: rgba(59, 130, 246, 0.1);
    }

    .role-switch-btn.active {
        background: #3b82f6;
        border-color: #3b82f6;
        color: #ffffff;
        box-shadow: 0 3px 10px rgba(59, 130, 246, 0.3);
    }

    .guide-header {
        padding: 24px 28px;
        border: 1px solid var(--border);
        border-radius: 16px;
        background: var(--surface);
        box-shadow: 0 8px 24px var(--shadow);
    }

    .guide-header-title {
        display: flex;
        align-items: flex-start;
        gap: 16px;
    }

    .guide-icon {
        font-size: 36px;
        line-height: 1;
    }

    .guide-header-title h2 {
        margin: 0 0 6px;
        font-size: 24px;
        color: var(--navy);
    }

    .guide-header-title p {
        margin: 0;
        color: var(--muted);
        font-size: 14px;
        line-height: 1.6;
    }

    .guide-section {
        padding: 24px 28px;
        border: 1px solid var(--border);
        border-radius: 16px;
        background: var(--surface);
        box-shadow: 0 6px 20px var(--shadow);
    }

    .guide-section h3 {
        margin: 0 0 18px;
        font-size: 17px;
        color: var(--navy);
        border-bottom: 1px solid var(--border);
        padding-bottom: 12px;
    }

    .step-cards-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
        gap: 16px;
    }

    .step-card {
        padding: 18px;
        border-radius: 14px;
        background: var(--surface-alt);
        border: 1px solid var(--border);
        display: flex;
        flex-direction: column;
        gap: 8px;
    }

    .step-badge {
        display: inline-block;
        padding: 3px 10px;
        border-radius: 6px;
        background: #3b82f6;
        color: #ffffff;
        font-size: 11px;
        font-weight: 800;
        align-self: flex-start;
    }

    .step-card h4 {
        margin: 0;
        font-size: 14px;
        color: var(--navy);
    }

    .step-card p {
        margin: 0;
        font-size: 13px;
        color: var(--muted);
        line-height: 1.55;
    }

    .quick-actions-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
        gap: 14px;
    }

    .quick-action-btn {
        display: flex;
        flex-direction: column;
        gap: 4px;
        padding: 16px 20px;
        border-radius: 14px;
        border: 1px solid var(--border);
        background: var(--surface-alt);
        color: inherit;
        text-decoration: none;
        transition: all 0.2s ease;
    }

    .quick-action-btn:hover {
        border-color: #3b82f6;
        transform: translateY(-2px);
        box-shadow: 0 6px 16px var(--shadow);
    }

    .quick-action-btn strong {
        font-size: 14px;
        color: var(--navy);
    }

    .quick-action-btn span {
        font-size: 12px;
        color: var(--muted);
    }

    .status-action-list {
        display: flex;
        flex-direction: column;
        gap: 12px;
    }

    .status-action-item {
        display: flex;
        align-items: flex-start;
        gap: 14px;
        padding: 14px 18px;
        border-radius: 12px;
        border: 1px solid var(--border);
        background: var(--surface-alt);
    }

    .status-label {
        display: inline-block;
        padding: 4px 12px;
        border-radius: 999px;
        font-size: 12px;
        font-weight: 700;
        white-space: nowrap;
    }

    .badge-info {
        background: #e0f2fe;
        color: #0369a1;
    }

    .badge-success {
        background: #dcfce7;
        color: #15803d;
    }

    .badge-warn {
        background: #fef9c3;
        color: #854d0e;
    }

    .status-action-item p {
        margin: 0;
        font-size: 13px;
        color: var(--text);
        line-height: 1.5;
    }

    .faq-accordion {
        display: flex;
        flex-direction: column;
        gap: 10px;
    }

    .faq-item {
        border: 1px solid var(--border);
        border-radius: 12px;
        background: var(--surface-alt);
        overflow: hidden;
    }

    .faq-item summary {
        padding: 14px 18px;
        font-size: 14px;
        font-weight: 600;
        color: var(--navy);
        cursor: pointer;
        list-style: none;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }

    .faq-item summary::-webkit-details-marker {
        display: none;
    }

    .faq-item summary::after {
        content: '+';
        font-weight: 700;
        font-size: 16px;
        color: var(--muted);
    }

    .faq-item[open] summary::after {
        content: '−';
    }

    .faq-item p {
        margin: 0;
        padding: 0 18px 16px;
        font-size: 13px;
        color: var(--muted);
        line-height: 1.6;
        border-top: 1px solid var(--border);
        padding-top: 12px;
    }

    .info-notice-box {
        padding: 16px 20px;
        border-radius: 12px;
        border: 1px solid var(--border);
        background: var(--surface-alt);
    }
</style>
@endpush

@section('content')
    <div class="page-header">
        <h1>Panduan Penggunaan</h1>
        <p>
            Selamat datang, {{ $user->name }}. Informasi panduan di bawah ditampilkan khusus dan relevan untuk peran akun Anda.
        </p>
    </div>

    <div class="panduan-container">
        @if ($isSuperadmin || count($allowedRoles) > 1)
            <div class="role-switcher-card">
                <p class="role-switcher-label">
                    {{ $isSuperadmin ? 'Lihat Panduan Sebagai:' : 'Pilih Peran Panduan:' }}
                </p>
                <div class="role-switcher-buttons">
                    @foreach ($allowedRoles as $roleCode)
                        <a
                            href="{{ route('panduan', ['role' => $roleCode]) }}"
                            class="role-switch-btn {{ $activeRole === $roleCode ? 'active' : '' }}"
                        >
                            {{ $allRoles[$roleCode] ?? $roleCode }}
                        </a>
                    @endforeach
                </div>
            </div>
        @endif

        @if (view()->exists("panduan.partials.{$activeRole}"))
            @include("panduan.partials.{$activeRole}")
        @else
            @include("panduan.partials.client")
        @endif
    </div>
@endsection
