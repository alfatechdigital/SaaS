<?php

namespace App\Enums;

use App\Models\PlatformAuditLog;

/**
 * The platform-layer actions recorded in `platform_audit_logs`.
 *
 * The stored values are stable strings rather than enum case names: an audit
 * trail is read years later, and operators filter the log by these values.
 *
 * @see PlatformAuditLog
 * @see \docs\implementation\phase-02-tenant-context.md tugas 2.4
 */
enum PlatformAuditAction: string
{
    case TenantCreated = 'tenant.created';
    case TenantDeleted = 'tenant.deleted';
    case PublicPageEnabled = 'tenant.public_page.enabled';
    case PublicPageDisabled = 'tenant.public_page.disabled';

    /**
     * Get the display label for the action.
     */
    public function label(): string
    {
        return match ($this) {
            self::TenantCreated => 'Tenant dibuat',
            self::TenantDeleted => 'Tenant dihapus',
            self::PublicPageEnabled => 'Halaman publik diaktifkan',
            self::PublicPageDisabled => 'Halaman publik dinonaktifkan',
        };
    }
}
