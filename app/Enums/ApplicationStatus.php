<?php

namespace App\Enums;

enum ApplicationStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case AdminReview = 'admin_review';
    case RevisionRequested = 'revision_requested';
    case ClientRevision = 'client_revision';
    case Rejected = 'rejected';
    case ApplicationApproved = 'application_approved';
    case InvoiceProcess = 'invoice_process';
    case PaymentPartial = 'payment_partial';
    case PaymentCompleted = 'payment_completed';
    case Stage1Audit = 'stage_1_audit';
    case Stage2Audit = 'stage_2_audit';
    case QmsAudit = 'qms_audit';
    case CorrectiveAction = 'corrective_action';
    case CorrectiveRevision = 'corrective_revision';
    case CertificateReview = 'certificate_review';
    case FinalCertificate = 'final_certificate';
    case Completed = 'completed';
    case Surveillance = 'surveillance';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Submitted => 'Terkirim',
            self::AdminReview => 'Review Admin',
            self::RevisionRequested => 'Revisi Diminta',
            self::ClientRevision => 'Revisi Klien',
            self::Rejected => 'Ditolak',
            self::ApplicationApproved => 'Permohonan Disetujui',
            self::InvoiceProcess => 'Proses Invoice',
            self::PaymentPartial => 'Pembayaran Sebagian',
            self::PaymentCompleted => 'Pembayaran Selesai',
            self::Stage1Audit => 'Audit Tahap 1',
            self::Stage2Audit => 'Audit Tahap 2',
            self::QmsAudit => 'Audit Lapangan/QMS',
            self::CorrectiveAction => 'Tindakan Koreksi',
            self::CorrectiveRevision => 'Revisi Tindakan Koreksi',
            self::CertificateReview => 'Review Draft Sertifikat',
            self::FinalCertificate => 'Sertifikat Final',
            self::Completed => 'Selesai',
            self::Surveillance => 'Surveillance',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Completed, self::ApplicationApproved, self::PaymentCompleted, self::FinalCertificate => 'success',
            self::Rejected => 'danger',
            self::RevisionRequested, self::CorrectiveRevision => 'warning',
            self::Draft => 'neutral',
            default => 'info',
        };
    }

    public static function labelFor(string $status): string
    {
        return self::tryFrom($status)?->label()
            ?? str($status)->replace('_', ' ')->title()->toString();
    }
}
