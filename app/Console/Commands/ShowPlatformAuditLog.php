<?php

namespace App\Console\Commands;

use App\Enums\PlatformAuditAction;
use App\Models\PlatformAuditLog;
use Illuminate\Console\Command;

/**
 * Lists recent platform audit entries for an operator (Fase 2, tugas 2.4.4).
 *
 * A console listing is deliberate: only the operator who performs these actions
 * reads it, so a query is enough. A panel for it would be more surface than the
 * job needs — the plan explicitly asks not to build one.
 */
class ShowPlatformAuditLog extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'platform:audit-log
                            {--limit=20 : Jumlah entri terbaru yang ditampilkan}
                            {--action= : Filter satu jenis aksi, mis. tenant.deleted}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Tampilkan catatan aksi platform terbaru';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        /** @var string|null $filter */
        $filter = $this->option('action');

        if ($filter !== null && PlatformAuditAction::tryFrom($filter) === null) {
            $this->components->error("Aksi tidak dikenal: {$filter}");
            $this->components->info(
                'Nilai yang dikenal: '.implode(', ', array_column(PlatformAuditAction::cases(), 'value')),
            );

            return self::FAILURE;
        }

        $query = PlatformAuditLog::query()
            ->with('actor:id,name,email')
            ->latest('created_at')
            ->latest('id')
            ->limit((int) $this->option('limit'));

        if ($filter !== null) {
            $query->where('action', $filter);
        }

        $logs = $query->get();

        if ($logs->isEmpty()) {
            $this->components->info('Belum ada catatan aksi platform.');

            return self::SUCCESS;
        }

        $this->table(
            ['Waktu', 'Aktor', 'Aksi', 'Target', 'Detail', 'IP'],
            $logs->map(fn (PlatformAuditLog $log): array => [
                $log->created_at?->toDateTimeString() ?? '-',
                $log->actor->email ?? '(tidak diketahui)',
                $log->action->value,
                sprintf('%s#%s', class_basename($log->target_type), $log->target_id ?? '-'),
                (string) json_encode($log->details ?? []),
                $log->ip_address ?? '-',
            ])->all(),
        );

        return self::SUCCESS;
    }
}
