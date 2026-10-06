<?php

namespace App\Actions\Platform;

use App\Enums\PlatformAuditAction;
use App\Models\PlatformAuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Writes one entry into the platform audit trail (Fase 2, tugas 2.4).
 *
 * Every installation-wide action an operator performs should leave a row here:
 * who did it, to which record, and from where. The actor defaults to the
 * authenticated user and the address to the current request, so call sites stay
 * readable — console code can still pass both explicitly.
 *
 * @see PlatformAuditLog
 * @see \docs\implementation\phase-02-tenant-context.md tugas 2.4.2
 */
final class RecordPlatformAudit
{
    /**
     * @param  Model  $target  the record the action was performed on
     * @param  array<string, mixed>  $details  context worth keeping, e.g. the tenant name
     */
    public function handle(
        PlatformAuditAction $action,
        Model $target,
        array $details = [],
        ?User $actor = null,
        ?string $ipAddress = null,
    ): PlatformAuditLog {
        $user = $actor ?? Auth::user();

        return PlatformAuditLog::create([
            'actor_id' => $user instanceof User ? $user->id : null,
            'action' => $action,
            'target_type' => $target->getMorphClass(),
            'target_id' => (string) $target->getKey(),
            'details' => $details === [] ? null : $details,
            'ip_address' => $ipAddress ?? $this->currentAddress(),
        ]);
    }

    /**
     * The address the action came from, or null when a console run did it.
     *
     * The console kernel binds a `request` instance too, so "is a request bound?"
     * would have recorded the loopback address as if an operator had used the
     * panel from it. Coming from the console is the honest distinction.
     */
    private function currentAddress(): ?string
    {
        return app()->runningInConsole() ? null : request()->ip();
    }
}
