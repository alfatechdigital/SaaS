<?php

namespace App\Enums;

use App\Support\TenantContext;

/**
 * How the active tenant was resolved for the current request or job.
 *
 * The origin is kept explicit so that "cross-tenant work" is visible in the code
 * instead of being an accident: `Path` and `Host` come from an inbound user
 * request, while `Console` and `Platform` mean the code deliberately stepped
 * outside the boundary of a single tenant.
 *
 * @see TenantContext
 * @see \docs\implementation\phase-02-tenant-context.md tugas 2.1.1
 */
enum TenantContextOrigin: string
{
    /** Resolved from the `{current_team}` route parameter. */
    case Path = 'path';

    /** Resolved from the request host / custom domain (Fase 3, belum dipakai). */
    case Host = 'host';

    /** Set explicitly by a console command, queued job, or seeder. */
    case Console = 'console';

    /** Platform layer: an operator acting across tenants. */
    case Platform = 'platform';
}
