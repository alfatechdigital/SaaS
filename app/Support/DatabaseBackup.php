<?php

namespace App\Support;

use App\Console\Commands\BackupDatabase;
use App\Console\Commands\RestoreDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * Dumps the configured database to a file — and puts one back.
 *
 * One database holds every tenant, so a single unusable backup affects the whole
 * customer list. That is why the plan asks for a backup whose *restore* is
 * proven rather than assumed (P-5). SQLite is copied as a file; MySQL and
 * PostgreSQL go through their client binaries (`mysqldump`/`pg_dump` and
 * `mysql`/`psql`), which must therefore be installed on the host.
 *
 * @see BackupDatabase
 * @see RestoreDatabase
 * @see \docs\syarhul-implementation-urgent.md P-5
 */
final class DatabaseBackup
{
    /**
     * Directory the dumps are written to.
     */
    public function directory(): string
    {
        return (string) config('backup.path');
    }

    /**
     * Dump a connection and return the path of the file that was written.
     */
    public function dump(?string $connection = null): string
    {
        $name = $this->connectionName($connection);
        [$driver, $config] = $this->resolve($name);

        $this->ensureDirectoryExists();

        $path = $this->directory().DIRECTORY_SEPARATOR.sprintf(
            'backup-%s-%s.%s',
            $name,
            now()->format('Y-m-d_H-i-s'),
            $driver === 'sqlite' ? 'sqlite' : 'sql',
        );

        switch ($driver) {
            case 'sqlite':
                $this->copySqlite($config, $path);
                break;
            case 'mysql':
            case 'mariadb':
                $this->runMysqlDump($config, $path);
                break;
            case 'pgsql':
                $this->runPostgresDump($config, $path);
                break;
            default:
                throw new RuntimeException("Driver [{$driver}] belum didukung untuk backup.");
        }

        return $path;
    }

    /**
     * Replace the data of a connection with the contents of a dump.
     *
     * Destructive by nature: whatever the file holds becomes the database.
     */
    public function restore(string $file, ?string $connection = null): void
    {
        if (! is_file($file)) {
            throw new RuntimeException("Berkas backup tidak ditemukan: {$file}");
        }

        $name = $this->connectionName($connection);
        [$driver, $config] = $this->resolve($name);

        // Drop the pooled connection first: SQLite swaps the file underneath it,
        // and the client binaries need the schema free to be rewritten.
        DB::purge($name);

        switch ($driver) {
            case 'sqlite':
                $this->restoreSqlite($config, $file);
                break;
            case 'mysql':
            case 'mariadb':
                $this->runMysqlRestore($config, $file);
                break;
            case 'pgsql':
                $this->runPostgresRestore($config, $file);
                break;
            default:
                throw new RuntimeException("Driver [{$driver}] belum didukung untuk restore.");
        }

        DB::purge($name);
    }

    /**
     * Delete dumps older than the given number of days.
     *
     * @return list<string> the files that were removed
     */
    public function prune(int $keepDays): array
    {
        $removed = [];
        $threshold = now()->subDays($keepDays)->getTimestamp();

        foreach ($this->files() as $file) {
            $modifiedAt = filemtime($file);

            if ($modifiedAt === false || $modifiedAt >= $threshold) {
                continue;
            }

            if (unlink($file)) {
                $removed[] = $file;
            }
        }

        return $removed;
    }

    /**
     * Every dump in the backup directory, oldest name first.
     *
     * @return list<string>
     */
    public function files(): array
    {
        $files = glob($this->directory().DIRECTORY_SEPARATOR.'backup-*') ?: [];
        sort($files);

        return $files;
    }

    private function ensureDirectoryExists(): void
    {
        $directory = $this->directory();

        if (is_dir($directory)) {
            return;
        }

        if (! mkdir($directory, 0o775, true) && ! is_dir($directory)) {
            throw new RuntimeException("Tidak bisa membuat folder backup: {$directory}");
        }
    }

    private function connectionName(?string $connection): string
    {
        return $connection ?? (string) config('database.default');
    }

    /**
     * Resolve the driver and raw configuration of a connection.
     *
     * The configuration is read straight from config so a backup does not need a
     * live connection — SQLite only needs the path, and the client binaries make
     * their own connection.
     *
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function resolve(string $name): array
    {
        $config = config("database.connections.{$name}");

        if (! is_array($config)) {
            throw new RuntimeException("Koneksi database [{$name}] tidak dikenal.");
        }

        $driver = (string) ($config['driver'] ?? '');

        if ($driver === '') {
            throw new RuntimeException("Koneksi [{$name}] tidak punya driver.");
        }

        return [$driver, $config];
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function sqliteFile(array $config): string
    {
        $database = (string) ($config['database'] ?? '');

        if ($database === '' || str_contains($database, ':memory:')) {
            throw new RuntimeException('Database SQLite in-memory tidak punya berkas, jadi tidak bisa di-backup maupun dipulihkan.');
        }

        return $database;
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function copySqlite(array $config, string $path): void
    {
        $database = $this->sqliteFile($config);

        if (! is_file($database)) {
            throw new RuntimeException("Berkas database SQLite tidak ditemukan: {$database}");
        }

        if (! copy($database, $path)) {
            throw new RuntimeException("Gagal menyalin database ke {$path}.");
        }
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function restoreSqlite(array $config, string $file): void
    {
        $database = $this->sqliteFile($config);

        if (! copy($file, $database)) {
            throw new RuntimeException("Gagal memulihkan {$file} ke {$database}.");
        }
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function runMysqlDump(array $config, string $path): void
    {
        $process = new Process([
            'mysqldump',
            '--host='.$this->value($config, 'host'),
            '--port='.$this->value($config, 'port'),
            '--user='.$this->value($config, 'username'),
            '--single-transaction',
            '--skip-lock-tables',
            $this->value($config, 'database'),
        ], env: ['MYSQL_PWD' => $this->value($config, 'password')]);

        $this->writeToFile($process, $path, 'mysqldump');
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function runMysqlRestore(array $config, string $file): void
    {
        $process = new Process([
            'mysql',
            '--host='.$this->value($config, 'host'),
            '--port='.$this->value($config, 'port'),
            '--user='.$this->value($config, 'username'),
            $this->value($config, 'database'),
        ], env: ['MYSQL_PWD' => $this->value($config, 'password')]);

        $this->readFromFile($process, $file, 'mysql');
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function runPostgresDump(array $config, string $path): void
    {
        $process = new Process([
            'pg_dump',
            '--host='.$this->value($config, 'host'),
            '--port='.$this->value($config, 'port'),
            '--username='.$this->value($config, 'username'),
            '--no-password',
            '--dbname='.$this->value($config, 'database'),
        ], env: ['PGPASSWORD' => $this->value($config, 'password')]);

        $this->writeToFile($process, $path, 'pg_dump');
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function runPostgresRestore(array $config, string $file): void
    {
        $process = new Process([
            'psql',
            '--host='.$this->value($config, 'host'),
            '--port='.$this->value($config, 'port'),
            '--username='.$this->value($config, 'username'),
            '--no-password',
            // Without this, psql keeps going after the first error and the
            // restore ends up half-applied while still reporting success.
            '--set=ON_ERROR_STOP=on',
            '--dbname='.$this->value($config, 'database'),
        ], env: ['PGPASSWORD' => $this->value($config, 'password')]);

        $this->readFromFile($process, $file, 'psql');
    }

    /**
     * Stream a process' standard output into a file.
     *
     * Streamed rather than buffered: a database dump does not belong in memory.
     */
    private function writeToFile(Process $process, string $path, string $binary): void
    {
        $handle = fopen($path, 'wb');

        if ($handle === false) {
            throw new RuntimeException("Tidak bisa menulis berkas backup: {$path}");
        }

        try {
            $process->run(function (string $type, string $buffer) use ($handle): void {
                if ($type === Process::OUT) {
                    fwrite($handle, $buffer);
                }
            });
        } catch (RuntimeException $exception) {
            fclose($handle);
            $this->discard($path);

            throw new RuntimeException("Gagal menjalankan {$binary}: {$exception->getMessage()}", 0, $exception);
        }

        fclose($handle);

        if (! $process->isSuccessful()) {
            $message = trim($process->getErrorOutput());
            $this->discard($path);

            throw new RuntimeException("{$binary} gagal".($message === '' ? '.' : ": {$message}"));
        }
    }

    /**
     * Feed a dump file into a process' standard input.
     */
    private function readFromFile(Process $process, string $file, string $binary): void
    {
        $handle = fopen($file, 'rb');

        if ($handle === false) {
            throw new RuntimeException("Tidak bisa membaca berkas backup: {$file}");
        }

        try {
            $process->setInput($handle);
            $process->run();
        } catch (RuntimeException $exception) {
            fclose($handle);

            throw new RuntimeException("Gagal menjalankan {$binary}: {$exception->getMessage()}", 0, $exception);
        }

        fclose($handle);

        if (! $process->isSuccessful()) {
            $message = trim($process->getErrorOutput());

            throw new RuntimeException("{$binary} gagal".($message === '' ? '.' : ": {$message}"));
        }
    }

    private function discard(string $path): void
    {
        if (is_file($path)) {
            unlink($path);
        }
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function value(array $config, string $key): string
    {
        return (string) ($config[$key] ?? '');
    }
}
