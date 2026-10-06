<?php

namespace App\Console\Commands;

use App\Support\DatabaseBackup;
use Illuminate\Console\Command;
use Illuminate\Console\ConfirmableTrait;
use RuntimeException;

/**
 * Puts a dump back into the database (P-5).
 *
 * Restoring replaces whatever the database currently holds, so the command asks
 * for confirmation in production (`--force` skips it) — the panel this stage
 * belongs to has no UI for it on purpose: it is an operator action, not a
 * feature.
 */
class RestoreDatabase extends Command
{
    use ConfirmableTrait;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'db:restore
                            {file : Berkas backup yang akan dipulihkan}
                            {--connection= : Koneksi tujuan (default: koneksi utama)}
                            {--force : Jalankan tanpa konfirmasi di produksi}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Pulihkan database dari berkas backup (menimpa data saat ini)';

    /**
     * Execute the console command.
     */
    public function handle(DatabaseBackup $backup): int
    {
        if (! $this->confirmToProceed()) {
            return self::FAILURE;
        }

        $file = (string) $this->argument('file');
        $connection = $this->option('connection');

        try {
            $backup->restore($file, is_string($connection) && $connection !== '' ? $connection : null);
        } catch (RuntimeException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->components->info("Database dipulihkan dari: {$file}");

        return self::SUCCESS;
    }
}
