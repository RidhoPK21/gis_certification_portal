@extends('layouts.auth')

@section('title', 'Cek Status Permohonan')

@section('wrapper_class', 'auth-wrapper--wide')

@section('side_heading', 'Lacak permohonan sertifikasi Anda')

@section('side_subheading', 'Masukkan nomor permohonan untuk melihat sampai tahap mana prosesnya berjalan. Halaman ini terbuka tanpa perlu login dan tidak menampilkan data perusahaan maupun dokumen.')

@section('content')
    <style>
        /*
         * Hasil pelacakan bisa lebih tinggi dari layar; tanpa ini kartu
         * dipusatkan secara vertikal dan bagian atasnya terpotong saat discroll.
         */
        .auth-content {
            align-items: flex-start;
            padding-top: 42px;
            padding-bottom: 42px;
        }

        .track-badge {
            display: inline-block;
            padding: 5px 13px;
            border-radius: 999px;
            background: var(--light-blue);
            color: #0b5a92;
            font-size: 13px;
            font-weight: 700;
        }

        .track-summary {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            flex-wrap: wrap;
            padding-bottom: 18px;
            margin-bottom: 20px;
            border-bottom: 1px solid var(--border);
        }

        .track-qr {
            display: flex;
            gap: 20px;
            align-items: flex-start;
            flex-wrap: wrap;
            padding: 18px;
            margin-bottom: 22px;
            border: 1px solid var(--border);
            border-radius: 14px;
            background: #fbfdff;
        }

        /* Latar QR dikunci putih supaya kontrasnya tetap terbaca pemindai. */
        .track-qr-frame {
            padding: 10px;
            border: 1px solid var(--border);
            border-radius: 12px;
            background: #ffffff;
            line-height: 0;
        }

        .track-qr-info {
            flex: 1;
            min-width: 210px;
        }

        .track-step {
            display: flex;
            gap: 14px;
            padding-bottom: 18px;
        }

        .track-step-dot {
            flex: 0 0 auto;
            width: 13px;
            height: 13px;
            margin-top: 4px;
            border-radius: 999px;
            background: #cbd8e3;
        }

        .track-step.done .track-step-dot {
            background: #1c7a45;
        }

        .track-step.current .track-step-dot {
            background: var(--blue);
            box-shadow: 0 0 0 4px rgba(8, 120, 201, 0.16);
        }

        .track-step h4 {
            margin: 0;
            color: var(--navy);
            font-size: 15px;
        }

        .track-step p {
            margin: 3px 0 0;
            color: var(--muted);
            font-size: 13px;
            line-height: 1.6;
        }

        .track-dates {
            margin-top: 5px;
            color: #35506b;
            font-size: 12px;
        }

        .track-note {
            padding: 13px 15px;
            border: 1px solid #a9dfbd;
            border-radius: 11px;
            color: #17663a;
            background: #ecfbf2;
            font-size: 14px;
            line-height: 1.6;
        }

        .track-alert {
            margin-bottom: 20px;
            padding: 13px 15px;
            border: 1px solid #f0c2c2;
            border-radius: 11px;
            color: #a12626;
            background: #fdf2f2;
            font-size: 14px;
            line-height: 1.6;
        }
    </style>

    <section class="auth-card">
        <div class="auth-heading">
            <h2>Cek Status Permohonan</h2>

            <p>
                Masukkan nomor permohonan (nomor order) atau nomor sertifikat Anda.
            </p>
        </div>

        @if ($errors->any())
            <div class="track-alert">
                {{ $errors->first('order_number') }}
            </div>
        @endif

        @isset($notFound)
            <div class="track-alert">
                Nomor <strong>{{ $notFound }}</strong> tidak ditemukan. Periksa kembali penulisannya.
            </div>
        @endisset

        <form
            method="POST"
            action="{{ route('public.track') }}"
        >
            @csrf

            <div class="form-group">
                <label
                    class="form-label"
                    for="order_number"
                >
                    Nomor Permohonan / Nomor Sertifikat
                </label>

                <input
                    class="form-control"
                    id="order_number"
                    name="order_number"
                    value="{{ old('order_number', $notFound ?? '') }}"
                    placeholder="Contoh: 013/LSSM-GIS/V/2026"
                    autocomplete="off"
                    required
                    autofocus
                >
            </div>

            <button
                class="login-button"
                type="submit"
            >
                Cek Status
            </button>
        </form>

        <a
            class="secondary-button"
            href="{{ route('login') }}"
        >
            Masuk ke portal klien
        </a>
    </section>

    @isset($result)
        <section
            class="auth-card"
            style="margin-top: 22px;"
        >
            <div class="track-summary">
                <div>
                    <h2 style="margin: 0; color: var(--navy); font-size: 22px;">
                        Status {{ $result['order_number'] }}
                    </h2>

                    <p style="margin: 5px 0 0; color: var(--muted); font-size: 14px;">
                        {{ $result['scheme'] }} · Dikirim {{ $result['submitted_at'] ?: '-' }}
                    </p>
                </div>

                <span class="track-badge">{{ $result['status_label'] }}</span>
            </div>

            <div class="track-qr">
                <div class="track-qr-frame">
                    {!! $result['qr_svg'] !!}
                </div>

                <div class="track-qr-info">
                    <strong>QR pelacakan permohonan</strong>

                    <p style="margin: 7px 0 12px; color: var(--muted); font-size: 13px; line-height: 1.6;">
                        Pindai QR ini untuk membuka halaman progres yang sama di perangkat lain.
                        QR hanya menampilkan tahapan dan tanggal, <strong>tidak memberi akses ke
                        dokumen atau berkas sertifikat</strong>.
                    </p>

                    <a
                        class="secondary-button"
                        style="margin-top: 0;"
                        href="{{ $result['qr_download_url'] }}"
                    >
                        Unduh QR
                    </a>
                </div>
            </div>

            <h3 style="margin: 0 0 16px; color: var(--navy); font-size: 16px;">
                Progres Permohonan
            </h3>

            @foreach ($result['timeline'] as $item)
                <div class="track-step {{ $item['state'] }}">
                    <span class="track-step-dot"></span>

                    <div>
                        <h4>{{ $item['label'] }}</h4>

                        <p>
                            @if ($item['state'] === 'done')
                                Tahap telah selesai.
                            @elseif ($item['state'] === 'current')
                                Sedang diproses oleh tim terkait.
                            @else
                                Menunggu tahap sebelumnya selesai.
                            @endif
                        </p>

                        @if ($item['started_at'])
                            <div class="track-dates">
                                Mulai {{ $item['started_at'] }}

                                @if ($item['finished_at'])
                                    · Selesai {{ $item['finished_at'] }}
                                @endif
                            </div>
                        @endif
                    </div>
                </div>
            @endforeach

            @if ($result['certificate_available'])
                <div class="track-note">
                    <strong>Sertifikat telah tersedia.</strong>

                    @if ($result['certificate_number'])
                        <div style="margin-top: 6px;">
                            Nomor sertifikat <strong>{{ $result['certificate_number'] }}</strong>

                            @if ($result['issued_date'])
                                · Terbit {{ $result['issued_date'] }}
                            @endif

                            @if ($result['expiry_date'])
                                · Berlaku sampai {{ $result['expiry_date'] }}
                            @endif
                        </div>
                    @endif

                    <div style="margin-top: 6px;">
                        Pengunduhan berkas sertifikat tetap melalui login klien atau link aman dari Tim Teknis.
                    </div>
                </div>
            @endif
        </section>
    @endisset
@endsection
