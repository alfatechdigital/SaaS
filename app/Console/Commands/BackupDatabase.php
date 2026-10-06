<?php

namespace App\Console\Commands;

use App\Support\DatabaseBackup;
use Illuminate\Console\Command;
use RuntimeException;

/**
 * Writes a database dump to the backup directory and prunes old ones (P-5).
 *
 * Runs daily from the scheduler; see `routes/console.php`. The scheduler itself
 * needs a cron entry (`php artisan schedule:run`) on the host — documented in
 * the README, because a scheduled command nobody runs is not a backup.
 */
class BackupDatabase extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'db:backup
                            {--connection= : Koneksi yang di-backup (default: koneksi utama)}
                            {--keep-days= : Hapus dump yang lebih tua dari sekian hari}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Backup database ke folder backup dan rapikan dump lama';

    /**
     * Execute the console command.
     */
    public function handle(DatabaseBackup $backup): int
    {
        $connection = $this->option('connection');
        $keepDays = $this->option('keep-days');

        try {
            $path = $backup->dump(is_string($connection) && $connection !== '' ? $connection : null);
        } catch (RuntimeException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->components->info("Backup dibuat: {$path}");

        $retention = is_numeric($keepDays) ? (int) $keepDays : (int) config('backup.keep_days');
        $removed = $backup->prune($retention);

        if ($removed !== []) {
            $this->components->info(sprintf(
                '%d dump lama dihapus (retensi %d hari).',
                count($removed),
                $retention,
            ));
        }

        return self::SUCCESS;
    }
}
