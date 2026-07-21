@extends('layouts.guest')

@section('title', 'Preview Draft Sertifikat')

@section('body')
    <style>
        .draft-preview-page { min-height: 100vh; background: #f3f7fb; }
        .draft-preview-header { background: #ffffff; border-bottom: 1px solid #dce5ee; }
        .draft-preview-nav {
            display: flex; align-items: center; justify-content: space-between;
            gap: 20px; padding-top: 16px; padding-bottom: 16px;
        }
        .draft-preview-brand { display: flex; align-items: center; gap: 12px; color: #062f57; font-weight: 700; }
        .draft-preview-brand-mark {
            display: inline-flex; align-items: center; justify-content: center;
            width: 46px; height: 46px; border-radius: 12px; background: #073a67;
            color: #ffffff; font-size: 15px; letter-spacing: 0.5px;
        }
        .draft-preview-brand small { display: block; margin-top: 3px; color: #64748b; font-size: 12px; font-weight: 500; }
        .draft-badge {
            display: inline-flex; align-items: center; padding: 8px 13px;
            border: 1px solid #f4c56b; border-radius: 999px; background: #fff7df;
            color: #8b5b00; font-size: 12px; font-weight: 700;
        }
        .draft-preview-content { width: min(1400px, calc(100% - 32px)); margin: 0 auto; padding: 20px 0 32px; }
        .draft-warning {
            margin-bottom: 16px; padding: 14px 16px; border: 1px solid #f1cf7a;
            border-radius: 12px; background: #fff9e9; color: #6f4d00; line-height: 1.6;
        }
        .draft-document-wrapper {
            position: relative; overflow: hidden; min-height: 760px;
            border: 1px solid #d7e1eb; border-radius: 14px; background: #dfe7ef;
            box-shadow: 0 12px 32px rgba(15, 45, 75, 0.10);
        }
        .draft-pdf-frame {
            display: block; width: 100%; height: calc(100vh - 190px);
            min-height: 760px; border: 0; background: #ffffff;
        }
        .draft-watermark-overlay {
            position: absolute; inset: 0; z-index: 5; display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr)); grid-auto-rows: 150px;
            align-content: space-around; overflow: hidden; pointer-events: none; user-select: none;
        }
        .draft-watermark-overlay span {
            display: flex; align-items: center; justify-content: center; padding: 12px;
            color: rgba(10, 52, 88, 0.13); font-size: 15px; font-weight: 700; text-align: center;
            text-transform: uppercase; transform: rotate(-24deg); white-space: nowrap;
        }
        @media (max-width: 768px) {
            .draft-preview-nav { align-items: flex-start; flex-direction: column; }
            .draft-watermark-overlay { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .draft-pdf-frame, .draft-document-wrapper { min-height: 620px; }
        }
    </style>

    <div class="draft-preview-page">
        <header class="draft-preview-header">
            <div class="container draft-preview-nav">
                <div class="draft-preview-brand">
                    <span class="draft-preview-brand-mark">GIS</span>
                    <span>
                        Preview Draft Sertifikat
                        <small>{{ $application->order_number ?? 'Nomor order belum tersedia' }}</small>
                    </span>
                </div>
                <span class="draft-badge">DRAFT — TIDAK BERLAKU SEBAGAI SERTIFIKAT</span>
            </div>
        </header>

        <main class="draft-preview-content">
            <div class="draft-warning">
                <strong>Dokumen draft untuk peninjauan.</strong>
                Dokumen ini belum berlaku sebagai sertifikat final.
                Seluruh akses preview dicatat oleh sistem.
            </div>

            <div class="draft-document-wrapper">
                <iframe
                    class="draft-pdf-frame"
                    src="{{ route('certificate.draft.stream', ['token' => $token]) }}#toolbar=0&navpanes=0&scrollbar=1"
                    title="Preview draft sertifikat {{ $application->order_number }}"
                    loading="eager"
                    referrerpolicy="same-origin"
                ></iframe>

                <div class="draft-watermark-overlay" aria-hidden="true">
                    @foreach (range(1, 15) as $watermarkIndex)
                        <span>{{ $application->company_name }} · {{ $application->order_number }} · DRAFT</span>
                    @endforeach
                </div>
            </div>
        </main>
    </div>

    <script>
        document.addEventListener('contextmenu', function (event) { event.preventDefault(); });
        document.addEventListener('keydown', function (event) {
            const key = String(event.key || '').toLowerCase();
            const hasModifier = event.ctrlKey || event.metaKey;
            if (hasModifier && ['p', 's'].includes(key)) { event.preventDefault(); }
        });
    </script>
@endsection
