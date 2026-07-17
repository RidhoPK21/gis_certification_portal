<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            [
                'code' => 'client',
                'name' => 'Klien',
                'description' =>
                    'Pengguna eksternal yang mengajukan sertifikasi.',
                'sort_order' => 10,
            ],
            [
                'code' => 'admin_application',
                'name' => 'Admin Permohonan',
                'description' =>
                    'Melakukan review, revisi, approval, dan penolakan permohonan.',
                'sort_order' => 20,
            ],
            [
                'code' => 'finance',
                'name' => 'Finance',
                'description' =>
                    'Mengelola invoice dan pembayaran.',
                'sort_order' => 30,
            ],
            [
                'code' => 'auditor',
                'name' => 'Auditor',
                'description' =>
                    'Mengelola tahap audit, temuan, dan corrective action.',
                'sort_order' => 40,
            ],
            [
                'code' => 'technical',
                'name' => 'Tim Teknis',
                'description' =>
                    'Mengelola draft dan sertifikat final.',
                'sort_order' => 50,
            ],
            [
                'code' => 'superadmin',
                'name' => 'Superadmin',
                'description' =>
                    'Mengelola seluruh konfigurasi dan modul sistem.',
                'sort_order' => 1,
            ],
        ];

        foreach ($roles as $roleData) {
            Role::query()->updateOrCreate(
                ['code' => $roleData['code']],
                array_merge(
                    $roleData,
                    ['is_active' => true]
                )
            );
        }

        $permissions = [
            ['dashboard.view', 'Melihat Dashboard', 'dashboard'],
            ['notifications.view', 'Melihat Notifikasi', 'notifications'],
            ['profile.manage', 'Mengelola Profil', 'profile'],

            ['applications.create', 'Membuat Permohonan', 'applications'],
            ['applications.view_own', 'Melihat Permohonan Sendiri', 'applications'],
            ['applications.revise_own', 'Memperbaiki Revisi Sendiri', 'applications'],
            ['corrective_actions.submit', 'Mengirim Corrective Action', 'corrective_action'],
            ['certificates.view_draft', 'Melihat Draft Sertifikat', 'certificate'],
            ['certificates.download_final', 'Mengunduh Sertifikat Final', 'certificate'],

            ['applications.review', 'Review Permohonan', 'applications'],
            ['applications.request_revision', 'Meminta Revisi', 'applications'],
            ['applications.approve', 'Menyetujui Permohonan', 'applications'],
            ['applications.reject', 'Menolak Permohonan', 'applications'],
            ['applications.generate_review_pdf', 'Generate PDF Tinjauan', 'applications'],
            ['applications.edit_order', 'Mengubah Nomor Order', 'applications'],
            ['applications.assign_auditor', 'Menugaskan Auditor', 'applications'],

            ['finance.view', 'Melihat Modul Finance', 'finance'],
            ['finance.manage_invoice', 'Mengelola Invoice', 'finance'],
            ['finance.manage_payment', 'Mengelola Pembayaran', 'finance'],

            ['audit.view_assigned', 'Melihat Penugasan Audit', 'audit'],
            ['audit.manage_stages', 'Mengelola Tahap Audit', 'audit'],
            ['audit.manage_findings', 'Mengelola Temuan Audit', 'audit'],
            ['audit.review_corrective_action', 'Review Corrective Action', 'audit'],

            ['technical.view', 'Melihat Modul Teknis', 'technical'],
            ['technical.manage_draft_certificate', 'Mengelola Draft Sertifikat', 'technical'],
            ['technical.manage_final_certificate', 'Mengelola Sertifikat Final', 'technical'],
            ['technical.manage_share_links', 'Mengelola Link Sertifikat', 'technical'],
            ['technical.manage_surveillance', 'Mengelola Jadwal Surveillance', 'technical'],

            ['users.manage', 'Mengelola User', 'superadmin'],
            ['roles.manage', 'Mengelola Role', 'superadmin'],
            ['schemes.manage', 'Mengelola Skema', 'superadmin'],
            ['forms.manage', 'Mengelola Form Dinamis', 'superadmin'],
            ['documents.manage', 'Mengelola Dokumen Wajib', 'superadmin'],
            ['products.manage', 'Mengelola Produk SNI', 'superadmin'],
            ['order_numbers.manage', 'Mengelola Nomor Order', 'superadmin'],
            ['pdf_templates.manage', 'Mengelola Template PDF', 'superadmin'],
            ['notification_templates.manage', 'Mengelola Template Notifikasi', 'superadmin'],
            ['workflow.manage', 'Mengelola Workflow', 'superadmin'],
            ['audit_trail.view', 'Melihat Audit Trail', 'superadmin'],
            ['settings.manage', 'Mengelola Pengaturan Sistem', 'superadmin'],
        ];

        foreach ($permissions as [$code, $name, $module]) {
            Permission::query()->updateOrCreate(
                ['code' => $code],
                [
                    'name' => $name,
                    'module' => $module,
                ]
            );
        }

        $matrix = [
            'client' => [
                'dashboard.view',
                'notifications.view',
                'profile.manage',
                'applications.create',
                'applications.view_own',
                'applications.revise_own',
                'corrective_actions.submit',
                'certificates.view_draft',
                'certificates.download_final',
            ],

            'admin_application' => [
                'dashboard.view',
                'notifications.view',
                'profile.manage',
                'applications.review',
                'applications.request_revision',
                'applications.approve',
                'applications.reject',
                'applications.generate_review_pdf',
                'applications.edit_order',
                'applications.assign_auditor',
            ],

            'finance' => [
                'dashboard.view',
                'notifications.view',
                'profile.manage',
                'finance.view',
                'finance.manage_invoice',
                'finance.manage_payment',
            ],

            'auditor' => [
                'dashboard.view',
                'notifications.view',
                'profile.manage',
                'audit.view_assigned',
                'audit.manage_stages',
                'audit.manage_findings',
                'audit.review_corrective_action',
            ],

            'technical' => [
                'dashboard.view',
                'notifications.view',
                'profile.manage',
                'technical.view',
                'technical.manage_draft_certificate',
                'technical.manage_final_certificate',
                'technical.manage_share_links',
                'technical.manage_surveillance',
            ],
        ];

        foreach ($matrix as $roleCode => $permissionCodes) {
            $role = Role::query()
                ->where('code', $roleCode)
                ->firstOrFail();

            $permissionIds = Permission::query()
                ->whereIn('code', $permissionCodes)
                ->pluck('id');

            $role->permissions()->sync($permissionIds);
        }

        $superadmin = Role::query()
            ->where('code', 'superadmin')
            ->firstOrFail();

        $superadmin
            ->permissions()
            ->sync(Permission::query()->pluck('id'));
    }
}