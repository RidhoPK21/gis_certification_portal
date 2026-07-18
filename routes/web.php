<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
});

Route::middleware([
    'auth',
    'active',
])->group(function (): void {
    Route::get('/dashboard', DashboardController::class)
        ->name('dashboard');

    Route::view(
        '/notifications',
        'module.placeholder',
        [
            'title' => 'Notifikasi',
            'description' =>
                'Daftar notifikasi proses sertifikasi Anda.',
        ]
    )->name('notifications.index');

    Route::get('/profile', [ProfileController::class, 'edit'])
        ->name('profile.edit');
    Route::put('/profile', [ProfileController::class, 'update'])
        ->name('profile.update');
    Route::put('/profile/password', [ProfileController::class, 'password'])
        ->name('profile.password');

    Route::middleware('role:client')
        ->prefix('client')
        ->name('client.')
        ->group(function (): void {
            Route::view(
                '/applications/schemes',
                'module.placeholder',
                [
                    'title' => 'Ajukan Sertifikasi',
                    'description' =>
                        'Pilih skema sertifikasi untuk membuat permohonan baru.',
                ]
            )->name('applications.schemes');

            Route::view(
                '/applications',
                'module.placeholder',
                [
                    'title' => 'Permohonan Saya',
                    'description' =>
                        'Melihat draft, status, revisi, dan timeline permohonan.',
                ]
            )->name('applications.index');

            Route::view(
                '/corrective-actions',
                'module.placeholder',
                [
                    'title' => 'Corrective Action',
                    'description' =>
                        'Mengunggah bukti perbaikan atas temuan auditor.',
                ]
            )->name('corrective-actions.index');
        });

    Route::middleware(
        'role:admin_application,superadmin'
    )
        ->prefix('internal/applications')
        ->name('internal.applications.')
        ->group(function (): void {
            Route::view(
                '/',
                'module.placeholder',
                [
                    'title' => 'Review Permohonan',
                    'description' =>
                        'Review form, dokumen, revisi, approval, penolakan, dan PDF tinjauan.',
                ]
            )->name('index');
        });

    Route::middleware('role:finance,superadmin')
        ->prefix('internal/finance')
        ->name('finance.')
        ->group(function (): void {
            Route::view(
                '/',
                'module.placeholder',
                [
                    'title' => 'Invoice & Pembayaran',
                    'description' =>
                        'Mengelola invoice, pembayaran, dan milestone pembayaran.',
                ]
            )->name('index');
        });

    Route::middleware('role:auditor,superadmin')
        ->prefix('internal/audit')
        ->name('audit.')
        ->group(function (): void {
            Route::view(
                '/',
                'module.placeholder',
                [
                    'title' => 'Audit & Corrective Action',
                    'description' =>
                        'Mengelola Stage 1, Stage 2, hasil audit, temuan, dan corrective action.',
                ]
            )->name('index');
        });

    Route::middleware('role:technical,superadmin')
        ->prefix('internal/technical')
        ->name('technical.')
        ->group(function (): void {
            Route::view(
                '/',
                'module.placeholder',
                [
                    'title' => 'Sertifikat',
                    'description' =>
                        'Mengelola draft sertifikat, sertifikat final, link, expiry, dan surveillance.',
                ]
            )->name('index');
        });

    Route::middleware('role:superadmin')
        ->prefix('superadmin')
        ->name('superadmin.')
        ->group(function (): void {
            Route::view(
                '/users',
                'module.placeholder',
                [
                    'title' => 'User & Role',
                    'description' =>
                        'Mengelola pengguna, status akun, dan role.',
                ]
            )->name('users.index');

            Route::view(
                '/schemes',
                'module.placeholder',
                [
                    'title' => 'Master Skema',
                    'description' =>
                        'Mengelola skema, form dinamis, field, dan dokumen wajib.',
                ]
            )->name('schemes.index');

            Route::view(
                '/sni-products',
                'module.placeholder',
                [
                    'title' => 'Produk SNI',
                    'description' =>
                        'Mengelola master produk SNI dan data import.',
                ]
            )->name('sni-products.index');

            Route::view(
                '/audit-trail',
                'module.placeholder',
                [
                    'title' => 'Audit Trail',
                    'description' =>
                        'Melihat riwayat aktivitas dan perubahan sistem.',
                ]
            )->name('audit-trail.index');

            Route::view(
                '/settings',
                'module.placeholder',
                [
                    'title' => 'Pengaturan Sistem',
                    'description' =>
                        'Mengelola nomor order, workflow, template PDF, dan notifikasi.',
                ]
            )->name('settings.index');
        });
});