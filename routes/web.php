<?php

use App\Http\Controllers\Client\ApplicationController as ClientApplicationController;
use App\Http\Controllers\Client\DocumentController as ClientDocumentController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Superadmin\FormBuilderController;
use App\Http\Controllers\Superadmin\SchemeController;
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

    Route::get('/notifications', [NotificationController::class, 'index'])
        ->name('notifications.index');
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'read'])
        ->name('notifications.read');

    Route::get('/secure-files/application-document/{document}', [ClientDocumentController::class, 'download'])
        ->name('secure-files.application-document');

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
            Route::get('/applications', [ClientApplicationController::class, 'index'])->name('applications.index');
            Route::get('/applications/schemes', [ClientApplicationController::class, 'schemes'])->name('applications.schemes');
            Route::get('/applications/create/{scheme:slug}', [ClientApplicationController::class, 'create'])->name('applications.create');
            Route::post('/applications/create/{scheme:slug}', [ClientApplicationController::class, 'store'])->name('applications.store');
            Route::get('/applications/{application}', [ClientApplicationController::class, 'show'])->name('applications.show');
            Route::get('/applications/{application}/edit', [ClientApplicationController::class, 'edit'])->name('applications.edit');
            Route::put('/applications/{application}', [ClientApplicationController::class, 'update'])->name('applications.update');
            Route::post('/applications/{application}/submit', [ClientApplicationController::class, 'submit'])->name('applications.submit');
            Route::post('/applications/{application}/documents', [ClientDocumentController::class, 'store'])->name('documents.store');

            // Corrective Action dibangun pada Fase 6.
            Route::view('/corrective-actions', 'module.placeholder', [
                'title' => 'Corrective Action',
                'description' => 'Mengunggah bukti perbaikan atas temuan auditor.',
            ])->name('corrective-actions.index');
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

            Route::get('/schemes', [SchemeController::class, 'index'])
                ->name('schemes.index');
            Route::get('/schemes/{scheme}/edit', [SchemeController::class, 'edit'])
                ->name('schemes.edit');
            Route::put('/schemes/{scheme}', [SchemeController::class, 'update'])
                ->name('schemes.update');

            Route::get('/schemes/{scheme}/builder', [FormBuilderController::class, 'edit'])
                ->name('form-builder.edit');
            Route::post('/schemes/{scheme}/builder/sections', [FormBuilderController::class, 'storeSection'])
                ->name('form-builder.sections.store');
            Route::post('/schemes/{scheme}/builder/fields', [FormBuilderController::class, 'storeField'])
                ->name('form-builder.fields.store');
            Route::put('/schemes/{scheme}/builder/fields/{field}', [FormBuilderController::class, 'updateField'])
                ->name('form-builder.fields.update');
            Route::post('/schemes/{scheme}/builder/fields/{field}/toggle', [FormBuilderController::class, 'toggleField'])
                ->name('form-builder.fields.toggle');
            Route::post('/schemes/{scheme}/builder/documents', [FormBuilderController::class, 'storeDocument'])
                ->name('form-builder.documents.store');
            Route::put('/schemes/{scheme}/builder/documents/{document}', [FormBuilderController::class, 'updateDocument'])
                ->name('form-builder.documents.update');
            Route::post('/schemes/{scheme}/builder/documents/{document}/toggle', [FormBuilderController::class, 'toggleDocument'])
                ->name('form-builder.documents.toggle');

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