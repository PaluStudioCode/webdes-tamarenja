<?php

namespace App\Services;

use App\Exports\FullDatabaseBackupExport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use PDO;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

class DatabaseBackupService
{
    /**
     * Buat berkas backup database (.sql) di disk 'private' (folder backups/).
     * Menggunakan mysqldump bila tersedia, dan otomatis jatuh (fallback) ke
     * Pure PHP PDO SQL Dumper bila binary mysqldump tidak ditemukan/gagal atau $forcePdoFallback = true.
     *
     * @return array{path: string, method: 'mysqldump'|'pdo_fallback', size_kb: int}
     */
    public function createSqlBackup(bool $forcePdoFallback = false): array
    {
        $timestamp = now()->format('Y-m-d_His');
        $relativePath = 'backups/backup-sql-' . $timestamp . '-' . Str::uuid() . '.sql';

        $sqlContent = null;
        $method = 'pdo_fallback';

        if (! $forcePdoFallback) {
            $sqlContent = $this->tryMysqldump();
            if ($sqlContent !== null && str_contains($sqlContent, 'CREATE TABLE')) {
                $method = 'mysqldump';
            } else {
                $sqlContent = null;
            }
        }

        if ($sqlContent === null) {
            $sqlContent = $this->generatePdoSqlDump();
            $method = 'pdo_fallback';
        }

        Storage::disk('private')->put($relativePath, $sqlContent);
        $sizeBytes = strlen($sqlContent);

        return [
            'path' => $relativePath,
            'method' => $method,
            'size_kb' => max(1, (int) ceil($sizeBytes / 1024)),
        ];
    }

    /**
     * Hasilkan dump SQL murni berbasis PHP PDO (tanpa ketergantungan binary mysqldump).
     */
    public function generatePdoSqlDump(): string
    {
        $connection = DB::connection();
        $pdo = $connection->getPdo();
        $driver = $connection->getDriverName();
        $dbName = (string) $connection->getDatabaseName();

        $lines = [
            '-- ============================================================================',
            '-- Webdes Tamarenja Database Backup (Pure PHP PDO SQL Dumper)',
            '-- Database: ' . $dbName,
            '-- Generated At: ' . now()->toIso8601String(),
            '-- ============================================================================',
            '',
            'SET FOREIGN_KEY_CHECKS=0;',
            'SET SQL_MODE="NO_AUTO_VALUE_ON_ZERO";',
            '',
        ];

        $tables = $this->resolveTableNames($pdo, $driver);

        foreach ($tables as $table) {
            $quotedTable = '`' . str_replace('`', '``', $table) . '`';

            $lines[] = '-- ----------------------------------------------------------------------------';
            $lines[] = "-- Struktur tabel untuk {$quotedTable}";
            $lines[] = '-- ----------------------------------------------------------------------------';
            $lines[] = "DROP TABLE IF EXISTS {$quotedTable};";

            $createStatement = $this->resolveCreateTableSql($pdo, $driver, $table);
            if ($createStatement !== '') {
                $lines[] = rtrim($createStatement, ';') . ';';
            }
            $lines[] = '';

            $stmt = $pdo->query("SELECT * FROM {$quotedTable}");
            $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];

            if (! empty($rows)) {
                $columns = array_keys($rows[0]);
                $quotedColumns = implode(', ', array_map(
                    fn (string $col) => '`' . str_replace('`', '``', $col) . '`',
                    $columns
                ));

                $lines[] = "-- Data untuk tabel {$quotedTable} (" . count($rows) . ' baris)';

                foreach (array_chunk($rows, 100) as $chunk) {
                    $valueTuples = [];
                    foreach ($chunk as $row) {
                        $escapedValues = [];
                        foreach ($columns as $col) {
                            $val = $row[$col] ?? null;
                            $escapedValues[] = $this->escapeSqlValue($pdo, $val);
                        }
                        $valueTuples[] = '(' . implode(', ', $escapedValues) . ')';
                    }

                    $lines[] = "INSERT INTO {$quotedTable} ({$quotedColumns}) VALUES\n"
                        . implode(",\n", $valueTuples) . ';';
                }

                $lines[] = '';
            }
        }

        $lines[] = 'SET FOREIGN_KEY_CHECKS=1;';
        $lines[] = '-- End of SQL Dump';
        $lines[] = '';

        return implode("\n", $lines);
    }

    /**
     * Buat berkas export Excel penuh 8 lembar kerja (.xlsx) di disk 'private' (folder backups/).
     *
     * @return array{path: string, size_kb: int}
     */
    public function createExcelBackup(): array
    {
        $timestamp = now()->format('Y-m-d_His');
        $relativePath = 'backups/backup-excel-8sheet-' . $timestamp . '-' . Str::uuid() . '.xlsx';

        Excel::store(new FullDatabaseBackupExport(), $relativePath, 'private');

        $sizeBytes = Storage::disk('private')->exists($relativePath)
            ? (int) Storage::disk('private')->size($relativePath)
            : 1024;

        return [
            'path' => $relativePath,
            'size_kb' => max(1, (int) ceil($sizeBytes / 1024)),
        ];
    }

    /**
     * Coba jalankan binary mysqldump jika tersedia pada OS dan driver adalah mysql/mariadb.
     */
    private function tryMysqldump(): ?string
    {
        $connection = DB::connection();
        $driver = $connection->getDriverName();

        if (! in_array($driver, ['mysql', 'mariadb'], true)) {
            return null;
        }

        $finder = new ExecutableFinder();
        $binary = $finder->find('mysqldump');

        if (! $binary) {
            return null;
        }

        $config = $connection->getConfig();
        $host = (string) ($config['host'] ?? '127.0.0.1');
        $port = (string) ($config['port'] ?? '3306');
        $database = (string) ($config['database'] ?? '');
        $username = (string) ($config['username'] ?? '');
        $password = (string) ($config['password'] ?? '');
        $socket = (string) ($config['unix_socket'] ?? '');

        if ($database === '' || $username === '') {
            return null;
        }

        $command = [
            $binary,
            '--single-transaction',
            '--skip-lock-tables',
            '--quick',
            '--no-tablespaces',
            "--user={$username}",
        ];

        if ($socket !== '') {
            $command[] = "--socket={$socket}";
        } else {
            $command[] = "--host={$host}";
            $command[] = "--port={$port}";
        }

        $command[] = $database;

        try {
            $process = new Process($command, null, [
                'MYSQL_PWD' => $password,
            ], null, 60);

            $process->run();

            if (! $process->isSuccessful()) {
                return null;
            }

            $output = $process->getOutput();

            return trim($output) !== '' ? $output : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return list<string>
     */
    private function resolveTableNames(PDO $pdo, string $driver): array
    {
        if ($driver === 'sqlite') {
            $stmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name");

            return $stmt ? array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN)) : [];
        }

        $stmt = $pdo->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'");
        if (! $stmt) {
            return [];
        }

        $tables = [];
        while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
            if (! empty($row[0])) {
                $tables[] = (string) $row[0];
            }
        }

        return $tables;
    }

    private function resolveCreateTableSql(PDO $pdo, string $driver, string $table): string
    {
        if ($driver === 'sqlite') {
            $stmt = $pdo->prepare("SELECT sql FROM sqlite_master WHERE type='table' AND name = :name");
            $stmt->execute(['name' => $table]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            return (string) ($row['sql'] ?? '');
        }

        $quotedTable = '`' . str_replace('`', '``', $table) . '`';
        $stmt = $pdo->query("SHOW CREATE TABLE {$quotedTable}");
        $row = $stmt ? $stmt->fetch(PDO::FETCH_NUM) : null;

        return (string) ($row[1] ?? '');
    }

    private function escapeSqlValue(PDO $pdo, mixed $value): string
    {
        if ($value === null) {
            return 'NULL';
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        return $pdo->quote((string) $value);
    }
}
