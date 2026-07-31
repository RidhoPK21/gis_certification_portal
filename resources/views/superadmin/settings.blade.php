@extends('layouts.app')

@section('title', 'Pengaturan Sistem')

@section('content')
    <div class="page-head">
        <div>
            <h1>Pengaturan Sistem</h1>
            <p>Kelola identitas portal: logo, favicon, teks tampilan, footer, dan informasi kontak.</p>
        </div>
    </div>

    <form method="post" action="{{ route('superadmin.settings.update') }}" enctype="multipart/form-data">
        @csrf
        @method('PUT')

        <section class="card">
            <h2>Logo &amp; Favicon</h2>
            <p class="muted">Kosongkan bila tidak ingin mengubah. Format disarankan PNG latar transparan.</p>

            <div class="grid-3">
                <div class="form-group">
                    <label class="form-label">Logo Sidebar / Header</label>
                    @if ($logoUrl)
                        <div style="margin-bottom:8px">
                            <img src="{{ $logoUrl }}" alt="Logo" style="max-height:60px;background:#fff;padding:4px;border:1px solid var(--border);border-radius:8px">
                            <label class="small" style="display:block;margin-top:4px">
                                <input type="checkbox" name="remove_logo" value="1"> Hapus logo ini
                            </label>
                        </div>
                    @else
                        <div class="small muted" style="margin-bottom:8px">Belum ada logo — memakai tulisan nama aplikasi.</div>
                    @endif
                    <input class="form-control" type="file" name="logo" accept=".jpg,.jpeg,.png,.webp,.svg">
                    <div class="small muted">Maks 1 MB.</div>
                    @error('logo')<div class="error-text">{{ $message }}</div>@enderror
                </div>

                <div class="form-group">
                    <label class="form-label">Logo Halaman Login</label>
                    @if ($loginLogoUrl)
                        <div style="margin-bottom:8px">
                            <img src="{{ $loginLogoUrl }}" alt="Logo login" style="max-height:60px;background:#fff;padding:4px;border:1px solid var(--border);border-radius:8px">
                            <label class="small" style="display:block;margin-top:4px">
                                <input type="checkbox" name="remove_login_logo" value="1"> Hapus logo ini
                            </label>
                        </div>
                    @else
                        <div class="small muted" style="margin-bottom:8px">Belum ada — memakai logo bawaan GIS.</div>
                    @endif
                    <input class="form-control" type="file" name="login_logo" accept=".jpg,.jpeg,.png,.webp,.svg">
                    <div class="small muted">Maks 1 MB.</div>
                    @error('login_logo')<div class="error-text">{{ $message }}</div>@enderror
                </div>

                <div class="form-group">
                    <label class="form-label">Favicon (ikon tab browser)</label>
                    @if ($faviconUrl)
                        <div style="margin-bottom:8px">
                            <img src="{{ $faviconUrl }}" alt="Favicon" style="height:32px;background:#fff;padding:4px;border:1px solid var(--border);border-radius:8px">
                            <label class="small" style="display:block;margin-top:4px">
                                <input type="checkbox" name="remove_favicon" value="1"> Hapus favicon ini
                            </label>
                        </div>
                    @else
                        <div class="small muted" style="margin-bottom:8px">Belum ada — memakai favicon bawaan.</div>
                    @endif
                    <input class="form-control" type="file" name="favicon" accept=".png,.ico,.svg">
                    <div class="small muted">Maks 256 KB, disarankan 32×32 atau 64×64 piksel.</div>
                    @error('favicon')<div class="error-text">{{ $message }}</div>@enderror
                </div>
            </div>
        </section>

        <section class="card mt-2">
            <h2>Teks Identitas</h2>

            <div class="grid-2">
                <div class="form-group">
                    <label class="form-label">Nama Aplikasi <span class="required">*</span></label>
                    <input class="form-control" name="app_name" value="{{ old('app_name', $settings['app_name']) }}" required>
                    <div class="small muted">Tampil di sidebar dan judul tab browser.</div>
                    @error('app_name')<div class="error-text">{{ $message }}</div>@enderror
                </div>

                <div class="form-group">
                    <label class="form-label">Tagline</label>
                    <input class="form-control" name="app_tagline" value="{{ old('app_tagline', $settings['app_tagline']) }}">
                    <div class="small muted">Teks kecil di bawah nama aplikasi pada sidebar.</div>
                </div>

                <div class="form-group">
                    <label class="form-label">Nama Perusahaan <span class="required">*</span></label>
                    <input class="form-control" name="company_name" value="{{ old('company_name', $settings['company_name']) }}" required>
                    @error('company_name')<div class="error-text">{{ $message }}</div>@enderror
                </div>

                <div class="form-group">
                    <label class="form-label">Judul Halaman Login</label>
                    <input class="form-control" name="login_heading" value="{{ old('login_heading', $settings['login_heading']) }}">
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Deskripsi Halaman Login</label>
                <textarea class="form-textarea" name="login_subheading" rows="3">{{ old('login_subheading', $settings['login_subheading']) }}</textarea>
            </div>
        </section>

        <section class="card mt-2">
            <h2>Footer &amp; Kontak</h2>

            <div class="form-group">
                <label class="form-label">Teks Footer</label>
                <input class="form-control" name="footer_text" value="{{ old('footer_text', $settings['footer_text']) }}">
                <div class="small muted">Tampil di bagian bawah setiap halaman setelah login.</div>
            </div>

            <div class="grid-3">
                <div class="form-group">
                    <label class="form-label">Alamat</label>
                    <textarea class="form-textarea" name="contact_address" rows="2">{{ old('contact_address', $settings['contact_address']) }}</textarea>
                </div>

                <div class="form-group">
                    <label class="form-label">Telepon</label>
                    <input class="form-control" name="contact_phone" value="{{ old('contact_phone', $settings['contact_phone']) }}">
                </div>

                <div class="form-group">
                    <label class="form-label">Email</label>
                    <input class="form-control" type="email" name="contact_email" value="{{ old('contact_email', $settings['contact_email']) }}">
                    @error('contact_email')<div class="error-text">{{ $message }}</div>@enderror
                </div>
            </div>
        </section>

        <div class="mt-2">
            <button class="btn btn-primary">Simpan Pengaturan</button>
        </div>
    </form>
@endsection
