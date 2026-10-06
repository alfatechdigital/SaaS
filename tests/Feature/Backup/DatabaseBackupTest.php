<?php

namespace Tests\Feature\Backup;

use App\Support\DatabaseBackup;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use PDO;
use RuntimeException;
use Tests\TestCase;

/**
 * P-5 — a backup is only worth having if its restore works.
 *
 * These tests run against a throw-away SQLite file instead of the application
 * database, so the round trip is real: dump the file, change the data, put the
 * dump back, and read it again. MySQL and PostgreSQL go through their client
 * binaries (`mysqldump`, `pg_dump`, `mysql`, `psql`), which are not installed
 * everywhere — those paths are covered here by the connection checks and by the
 * documented procedure, not by an automated restore.
 *
 * @see \docs\syarhul-implementation-urgent.md P-5
 */
class DatabaseBackupTest extends TestCase
{
    private string $directory;

    private string $database;

    protected function setUp(): void
    {
        parent::setUp();

        $this->directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'alfatech-backup-'.uniqid();
        $this->database = $this->directory.DIRECTORY_SEPARATOR.'probe.sqlite';

        mkdir($this->directory, 0o775, true);

        config([
            'backup.path' => $this->directory.DIRECTORY_SEPARATOR.'backups',
            'database.connections.backup_probe' => [
                'driver' => 'sqlite',
                'database' => $this->database,
                'prefix' => '',
                'foreign_key_constraints' => true,
            ],
        ]);

        $this->runSql('CREATE TABLE probe (id INTEGER PRIMARY KEY, name TEXT)');
        $this->runSql("INSERT INTO probe (name) VALUES ('sebelum backup')");
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->directory);

        parent::tearDown();
    }

    public function test_the_dump_is_a_copy_of_the_database_file(): void
    {
        $path = $this->backup()->dump('backup_probe');

        $this->assertFileExists($path);
        $this->assertSame(file_get_contents($this->database), file_get_contents($path));
        $this->assertStringStartsWith('backup-backup_probe-', basename($path));
    }

    public function test_a_dump_can_be_restored_after_the_data_changed(): void
    {
        $dump = $this->backup()->dump('backup_probe');

        $this->runSql('DELETE FROM probe');
        $this->runSql("INSERT INTO probe (name) VALUES ('sesudah backup')");
        $this->assertSame(['sesudah backup'], $this->probeNames());

        $this->backup()->restore($dump, 'backup_probe');

        $this->assertSame(['sebelum backup'], $this->probeNames());
    }

    public function test_the_backup_command_creates_a_dump_and_reports_it(): void
    {
        $exitCode = Artisan::call('db:backup', ['--connection' => 'backup_probe']);
        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('Backup dibuat', $output);
        $this->assertCount(1, $this->backup()->files());
    }

    public function test_the_restore_command_puts_the_dump_back(): void
    {
        $dump = $this->backup()->dump('backup_probe');
        $this->runSql('DELETE FROM probe');

        $exitCode = Artisan::call('db:restore', [
            'file' => $dump,
            '--connection' => 'backup_probe',
            '--force' => true,
        ]);

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('Database dipulihkan', Artisan::output());
        $this->assertSame(['sebelum backup'], $this->probeNames());
    }

    public function test_the_backup_command_reports_a_missing_database_file(): void
    {
        config(['database.connections.backup_missing' => [
            'driver' => 'sqlite',
            'database' => $this->directory.DIRECTORY_SEPARATOR.'tidak-ada.sqlite',
        ]]);

        $exitCode = Artisan::call('db:backup', ['--connection' => 'backup_missing']);

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('tidak ditemukan', Artisan::output());
    }

    public function test_the_restore_command_reports_a_missing_dump(): void
    {
        $exitCode = Artisan::call('db:restore', [
            'file' => $this->directory.DIRECTORY_SEPARATOR.'tidak-ada.sqlite',
            '--force' => true,
        ]);

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('tidak ditemukan', Artisan::output());
    }

    public function test_pruning_removes_old_dumps_and_keeps_recent_ones(): void
    {
        $kept = $this->backup()->dump('backup_probe');

        $old = dirname($kept).DIRECTORY_SEPARATOR.'backup-backup_probe-2020-01-01_00-00-00.sqlite';
        file_put_contents($old, 'dump lama');
        touch($old, now()->subDays(30)->getTimestamp());

        $removed = $this->backup()->prune(7);

        $this->assertSame([$old], $removed);
        $this->assertFileDoesNotExist($old);
        $this->assertFileExists($kept);
    }

    public function test_an_in_memory_database_cannot_be_dumped(): void
    {
        config(['database.connections.backup_memory' => ['driver' => 'sqlite', 'database' => ':memory:']]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('in-memory');

        $this->backup()->dump('backup_memory');
    }

    public function test_an_unsupported_driver_is_refused(): void
    {
        config(['database.connections.backup_sqlsrv' => ['driver' => 'sqlsrv', 'database' => 'apa-saja']]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('belum didukung');

        $this->backup()->dump('backup_sqlsrv');
    }

    public function test_an_unknown_connection_is_refused(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('tidak dikenal');

        $this->backup()->dump('koneksi-yang-tidak-ada');
    }

    public function test_the_daily_backup_is_on_the_schedule(): void
    {
        Artisan::call('schedule:list');

        $this->assertStringContainsString('db:backup', Artisan::output());
    }

    private function backup(): DatabaseBackup
    {
        return app(DatabaseBackup::class);
    }

    private function runSql(string $sql): void
    {
        (new PDO('sqlite:'.$this->database))->exec($sql);
    }

    /**
     * @return list<string>
     */
    private function probeNames(): array
    {
        $statement = (new PDO('sqlite:'.$this->database))->query('SELECT name FROM probe ORDER BY id');

        if ($statement === false) {
            return [];
        }

        return array_map(strval(...), $statement->fetchAll(PDO::FETCH_COLUMN));
    }
}
