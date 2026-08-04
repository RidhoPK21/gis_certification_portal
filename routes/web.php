<?php

use App\Http\Controllers\Auth\AccountActivationController;
use App\Http\Controllers\Auth\PasswordResetOtpController;
use App\Http\Controllers\Auth\RegistrationOtpController;
use App\Http\Controllers\Client\ApplicationController as ClientApplicationController;
use App\Http\Controllers\Client\CorrectiveActionController as ClientCorrectiveActionController;
use App\Http\Controllers\Client\DocumentController as ClientDocumentController;
use App\Http\Controllers\CertificateShareController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Internal\ApplicationReviewController;
use App\Http\Controllers\Internal\AuditController;
use App\Http\Controllers\Internal\FinanceController;
use App\Http\Controllers\Internal\GeneratedPdfController;
use App\Http\Controllers\Internal\TechnicalController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PublicTrackingController;
use App\Http\Controllers\SecureFileController;
use App\Http\Controllers\Superadmin\AuditTrailController;
use App\Http\Controllers\Superadmin\FormBuilderController;
use App\Http\Controllers\Superadmin\SchemeController;
use App\Http\Controllers\Superadmin\SettingController;
use App\Http\Controllers\Superadmin\SniProductController;
use App\Http\Controllers\Superadmin\SniProductTaxonomyController;
use App\Http\Controllers\Superadmin\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
});

Route::get('/cek-status', [PublicTrackingController::class, 'index'])->name('public.home');
Route::post('/cek-status', [PublicTrackingController::class, 'track'])->middleware('throttle:15,1')->name('public.track');
Route::get('/cek-status/qr', [PublicTrackingController::class, 'qr'])->middleware('throttle:20,1')->name('public.qr');

Route::prefix('certificate')->group(function (): void {
    Route::get('/draft/{token}', [CertificateShareController::class, 'previewDraft'])->name('certificate.draft.preview');
    Route::get('/draft/{token}/stream', [CertificateShareController::class, 'streamDraft'])->middleware('throttle:30,1')->name('certificate.draft.stream');
    Route::get('/final/{token}', [CertificateShareController::class, 'finalAccess'])->name('certificate.final.access');
    Route::post('/final/{token}/download', [CertificateShareController::class, 'downloadFinal'])->middleware('throttle:8,1')->name('certificate.final.download');
});

Route::middleware('guest')->group(function (): void {
    Route::get('/register/verify', [RegistrationOtpController::class, 'show'])
        ->name('register.verify.show');
    Route::post('/register/verify', [RegistrationOtpController::class, 'verify'])
        ->name('register.verify.submit');
    Route::post('/register/verify/resend', [RegistrationOtpController::class, 'resend'])
        ->middleware('throttle:otp-resend-registration')
        ->name('register.verify.resend');

    Route::get('/activate-account', [AccountActivationController::class, 'show'])
        ->name('account.activate.show');
    Route::post('/activate-account', [AccountActivationController::class, 'activate'])
        ->name('account.activate.submit');
    Route::post('/activate-account/resend', [AccountActivationController::class, 'resend'])
        ->middleware('throttle:otp-resend-invite')
        ->name('account.activate.resend');

    Route::get('/reset-password', [PasswordResetOtpController::class, 'show'])
        ->name('password.reset.show');
    Route::post('/reset-password', [PasswordResetOtpController::class, 'reset'])
        ->name('password.reset.submit');
    Route::post('/reset-password/resend', [PasswordResetOtpController::class, 'resend'])
        ->middleware('throttle:otp-resend-invite')
        ->name('password.reset.resend');
});

Route::middleware([
    'auth',
    'active',
])->group(function (): void {
    Route::get('/dashboard', DashboardController::class)
        ->name('dashboard');

    Route::get('/panduan', function (\Illuminate\Http\Request $request) {
        $user = $request->user()->loadMissing('roles');
        $userRoles = $user->roles->pluck('code')->all();
        $primaryRole = $user->roles->sortBy('sort_order')->first();

        $allRoles = [
            'client' => 'Klien',
            'admin_application' => 'Admin Permohonan',
            'finance' => 'Finance',
            'auditor' => 'Auditor',
            'technical' => 'Tim Teknis',
            'superadmin' => 'Superadmin',
        ];

        $isSuperadmin = in_array('superadmin', $userRoles, true);
        $allowedRoles = $isSuperadmin ? array_keys($allRoles) : $userRoles;

        $requestedRole = (string) $request->query('role', '');
        $activeRole = in_array($requestedRole, $allowedRoles, true)
            ? $requestedRole
            : ($primaryRole?->code ?? 'client');

        return view('panduan', [
            'user' => $user,
            'userRoles' => $userRoles,
            'primaryRole' => $primaryRole,
            'allRoles' => $allRoles,
            'isSuperadmin' => $isSuperadmin,
            'allowedRoles' => $allowedRoles,
            'activeRole' => $activeRole,
        ]);
    })->name('panduan');

    Route::post('/notifications/read-all', [NotificationController::class, 'readAll'])
        ->name('notifications.read-all');
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'read'])
        ->name('notifications.read');

    Route::get('/secure-files/application-document/{document}', [ClientDocumentController::class, 'download'])
        ->name('secure-files.application-document');
    Route::get('/secure-files/application-field-file/{application}/{code}', [SecureFileController::class, 'fieldFile'])
        ->name('secure-files.application-field-file');
    Route::get('/secure-files/invoice/{invoice}', [SecureFileController::class, 'invoice'])
        ->name('secure-files.invoice');
    Route::get('/secure-files/audit/{file}', [SecureFileController::class, 'auditReport'])
        ->name('secure-files.audit');
    Route::get('/secure-files/corrective-action/{file}', [SecureFileController::class, 'correctiveAction'])
        ->name('secure-files.corrective-action');

    Route::get('/profile', [ProfileController::class, 'edit'])
        ->name('profile.edit');
    Route::put('/profile', [ProfileController::class, 'update'])
        ->name('profile.update');
    Route::put('/profile/password', [ProfileController::class, 'password'])
        ->name('profile.password');
    Route::post('/profile/signature', [ProfileController::class, 'signature'])
        ->name('profile.signature');
    Route::delete('/profile/signature', [ProfileController::class, 'removeSignature'])
        ->name('profile.signature.remove');
    Route::get('/profile/signature/preview', [ProfileController::class, 'signaturePreview'])
        ->name('profile.signature.preview');

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
            Route::post('/applications/{application}/upload-field-file', [ClientApplicationController::class, 'uploadFieldFile'])->name('applications.upload-field-file');
            Route::post('/applications/{application}/submit', [ClientApplicationController::class, 'submit'])->name('applications.submit');
            Route::delete('/applications/{application}', [ClientApplicationController::class, 'destroy'])->name('applications.destroy');
            Route::post('/applications/{application}/documents', [ClientDocumentController::class, 'store'])->name('documents.store');

            Route::get('/corrective-actions', [ClientCorrectiveActionController::class, 'index'])->name('corrective-actions.index');
            Route::post('/findings/{finding}/corrective-actions', [ClientCorrectiveActionController::class, 'store'])->name('corrective-actions.store');
        });

    Route::middleware('role:admin_application,superadmin')
        ->prefix('internal/applications')
        ->name('internal.')
        ->group(function (): void {
            Route::get('/', [ApplicationReviewController::class, 'index'])->name('applications.index');
            Route::get('/{application}', [ApplicationReviewController::class, 'show'])->name('applications.show');
            Route::post('/{application}/review', [ApplicationReviewController::class, 'saveReview'])->name('applications.review');
            Route::post('/{application}/forward-technical', [ApplicationReviewController::class, 'forwardToTechnical'])->name('applications.forward-technical');
            Route::post('/{application}/request-revision', [ApplicationReviewController::class, 'requestRevision'])->name('applications.revision');
            Route::post('/{application}/revisions/{revision}/resolve', [ApplicationReviewController::class, 'resolveRevision'])->name('applications.revisions.resolve');
            Route::post('/{application}/approve', [ApplicationReviewController::class, 'approve'])->name('applications.approve');
            Route::post('/{application}/reject', [ApplicationReviewController::class, 'reject'])->name('applications.reject');
            Route::post('/{application}/generate-pdf', [ApplicationReviewController::class, 'generatePdf'])->name('applications.generate-pdf');
            Route::put('/{application}/order', [ApplicationReviewController::class, 'updateOrder'])->name('applications.order');
            Route::get('/generated-pdf/{pdf}/download', [GeneratedPdfController::class, 'download'])->name('generated-pdf.download');
            Route::post('/{application}/audit-assignments', [ApplicationReviewController::class, 'assignAuditor'])->name('applications.audit-assignments.store');
        });

    Route::middleware('role:finance,superadmin')
        ->prefix('internal/finance')
        ->name('finance.')
        ->group(function (): void {
            Route::get('/', [FinanceController::class, 'index'])->name('index');
            Route::get('/{application}', [FinanceController::class, 'show'])->name('show');
            Route::post('/{application}/invoice', [FinanceController::class, 'saveInvoice'])->name('invoice');
            Route::post('/{application}/payment', [FinanceController::class, 'addPayment'])->name('payment');
        });

    Route::middleware('role:auditor,superadmin')
        ->prefix('internal/audit')
        ->name('audit.')
        ->group(function (): void {
            Route::get('/', [AuditController::class, 'index'])->name('index');
            Route::get('/{application}', [AuditController::class, 'show'])->name('show');
            Route::post('/{application}/stage', [AuditController::class, 'saveStage'])->name('stage');
            Route::post('/{application}/stage/skip', [AuditController::class, 'skipStage'])->name('stage.skip');
            Route::post('/{application}/complete', [AuditController::class, 'completeAudit'])->name('complete');
            Route::post('/{application}/findings', [AuditController::class, 'createFinding'])->name('findings.store');
            Route::post('/corrective-actions/{correctiveAction}/review', [AuditController::class, 'reviewCorrectiveAction'])->name('corrective-actions.review');
        });

    Route::middleware('role:technical,superadmin')
        ->prefix('internal/technical')
        ->name('technical.')
        ->group(function (): void {
            Route::get('/', [TechnicalController::class, 'index'])->name('index');
            Route::get('/reviews', [TechnicalController::class, 'reviewIndex'])->name('reviews.index');
            Route::get('/reviews/{application}', [TechnicalController::class, 'reviewShow'])->name('reviews.show');
            Route::post('/reviews/{application}', [TechnicalController::class, 'saveTechnicalReview'])->name('reviews.save');
            Route::post('/reviews/{application}/complete', [TechnicalController::class, 'completeTechnicalReview'])->name('reviews.complete');
            Route::get('/{application}', [TechnicalController::class, 'show'])->name('show');
            Route::post('/{application}/draft', [TechnicalController::class, 'uploadDraft'])->name('draft.upload');
            Route::post('/draft/{draft}/link', [TechnicalController::class, 'createDraftLink'])->name('draft.link');
            Route::post('/{application}/final', [TechnicalController::class, 'uploadFinal'])->name('final.upload');
            Route::post('/final/{final}/link', [TechnicalController::class, 'createFinalLink'])->name('final.link');
            Route::post('/{application}/complete', [TechnicalController::class, 'complete'])->name('complete');
            Route::post('/link/{link}/revoke', [TechnicalController::class, 'revoke'])->name('link.revoke');
            Route::post('/surveillance/{schedule}', [TechnicalController::class, 'updateSurveillance'])->name('surveillance.update');
        });

    Route::middleware('role:superadmin')
        ->prefix('superadmin')
        ->name('superadmin.')
        ->group(function (): void {
            Route::get('/users', [UserController::class, 'index'])->name('users.index');
            Route::get('/users/create', [UserController::class, 'create'])->name('users.create');
            Route::get('/users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
            Route::post('/users', [UserController::class, 'store'])->name('users.store');
            Route::post('/users/{user}/resend-invite', [UserController::class, 'resendInvite'])->name('users.resend-invite');
            Route::post('/users/{user}/password-reset', [UserController::class, 'sendPasswordReset'])->name('users.password-reset');
            Route::put('/users/{user}/password', [UserController::class, 'setPassword'])->name('users.password');
            Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
            Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy');

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

            Route::get('/sni-products', [SniProductController::class, 'index'])->name('sni-products.index');
            Route::post('/sni-products', [SniProductController::class, 'store'])->name('sni-products.store');
            Route::put('/sni-products/{product}', [SniProductController::class, 'update'])->name('sni-products.update');
            Route::post('/sni-products/import', [SniProductController::class, 'import'])->name('sni-products.import');

            Route::get('/sni-taxonomy', [SniProductTaxonomyController::class, 'index'])->name('sni-taxonomy.index');
            Route::post('/sni-taxonomy/groups', [SniProductTaxonomyController::class, 'storeGroup'])->name('sni-taxonomy.groups.store');
            Route::put('/sni-taxonomy/groups/{group}', [SniProductTaxonomyController::class, 'updateGroup'])->name('sni-taxonomy.groups.update');
            Route::post('/sni-taxonomy/categories', [SniProductTaxonomyController::class, 'storeCategory'])->name('sni-taxonomy.categories.store');
            Route::put('/sni-taxonomy/categories/{category}', [SniProductTaxonomyController::class, 'updateCategory'])->name('sni-taxonomy.categories.update');

            Route::get('/audit-trail', [AuditTrailController::class, 'index'])->name('audit-trail.index');

            Route::get('/settings', [SettingController::class, 'index'])->name('settings.index');
            Route::put('/settings', [SettingController::class, 'update'])->name('settings.update');
        });
});