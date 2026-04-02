<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Process\Process;
use ZipArchive;

class RestoreSpatieBackupCommand extends Command
{
    protected $signature = 'backup:restore
                            {zip? : Path to EspaceTayeb_*.zip (relative to project root or absolute)}
                            {--force : Skip confirmation}';

    protected $description = 'Extract a Spatie backup zip (password from BACKUP_ARCHIVE_PASSWORD) and import the DB dump.';

    public function handle(): int
    {
        $zipArg = $this->argument('zip');
        $zipPath = is_string($zipArg) && $zipArg !== ''
            ? (str_starts_with($zipArg, DIRECTORY_SEPARATOR) || preg_match('#^[A-Za-z]:[\\\\/]#', $zipArg) === 1
                ? $zipArg
                : base_path($zipArg))
            : $this->findNewestBackupZip();

        if ($zipPath === null || ! is_file($zipPath)) {
            $this->error('Backup zip not found. Pass the path, e.g. "storage/app/private/Espace tayeb/EspaceTayeb_2026-04-01-12-24-41.zip"');

            return self::FAILURE;
        }

        $zipPath = realpath($zipPath) ?: $zipPath;

        $password = config('backup.backup.password');
        if (blank($password)) {
            $this->error('Set BACKUP_ARCHIVE_PASSWORD in .env to match the backup archive.');

            return self::FAILURE;
        }

        if (! $this->option('force') && ! $this->confirm('Replace the current database with this backup?', false)) {
            return self::SUCCESS;
        }

        $workDir = storage_path('app/backup-restore-work/'.uniqid('restore_', true));
        File::ensureDirectoryExists($workDir);

        $this->info('Extracting: '.$zipPath);
        if (! $this->extractZip($zipPath, $workDir, (string) $password)) {
            File::deleteDirectory($workDir);
            $this->error('Extract failed (wrong password or bad zip).');

            return self::FAILURE;
        }

        $sqlFiles = $this->findDbDumpFiles($workDir);
        if ($sqlFiles === []) {
            File::deleteDirectory($workDir);
            $this->error('No SQL files under db-dumps/ in the archive.');

            return self::FAILURE;
        }

        $driver = config('database.default');
        $connectionConfig = config("database.connections.{$driver}");

        $this->line('Dumps: '.implode(', ', array_map('basename', $sqlFiles)));

        $sqlPath = $this->pickDumpForDriver($sqlFiles, $driver);
        if ($sqlPath === null) {
            File::deleteDirectory($workDir);
            $this->error("No dump matches driver [{$driver}].");

            return self::FAILURE;
        }

        $this->info('Importing: '.basename($sqlPath));

        $ok = match ($driver) {
            'sqlite' => $this->restoreSqlite($connectionConfig, $sqlPath),
            'mysql', 'mariadb' => $this->restoreMysql($connectionConfig, $sqlPath),
            default => $this->failUnsupportedDriver($driver, $sqlPath, $workDir),
        };

        File::deleteDirectory($workDir);

        if (! $ok) {
            return self::FAILURE;
        }

        $this->info('Done. Run: php artisan config:clear && php artisan cache:clear');

        return self::SUCCESS;
    }

    private function failUnsupportedDriver(string $driver, string $sqlPath, string $workDir): bool
    {
        File::deleteDirectory($workDir);
        $this->error("Driver [{$driver}] not supported. Import manually: {$sqlPath}");

        return false;
    }

    private function findNewestBackupZip(): ?string
    {
        $root = storage_path('app/private');
        if (! is_dir($root)) {
            return null;
        }

        $finder = new Finder;
        $finder->files()->in($root)->name('EspaceTayeb_*.zip');
        $newest = null;
        $mtime = 0;
        foreach ($finder as $file) {
            $t = $file->getMTime();
            if ($t >= $mtime) {
                $mtime = $t;
                $newest = $file->getRealPath();
            }
        }

        return $newest;
    }

    private function extractZip(string $zipPath, string $destDir, string $password): bool
    {
        $zip = new ZipArchive;
        if ($zip->open($zipPath, ZipArchive::RDONLY) !== true) {
            return false;
        }

        $zip->setPassword($password);
        $ok = $zip->extractTo($destDir);
        $zip->close();

        return $ok;
    }

    /**
     * @return list<string>
     */
    private function findDbDumpFiles(string $workDir): array
    {
        $finder = new Finder;
        $finder->files()->in($workDir)->path('db-dumps')->name('*.sql');

        $out = [];
        foreach ($finder as $file) {
            $out[] = $file->getRealPath() ?: $file->getPathname();
        }

        return $out;
    }

    /**
     * @param  list<string>  $sqlFiles
     */
    private function pickDumpForDriver(array $sqlFiles, string $driver): ?string
    {
        $prefix = match ($driver) {
            'sqlite' => 'sqlite-',
            'mysql', 'mariadb' => 'mysql-',
            default => '',
        };

        if ($prefix !== '') {
            foreach ($sqlFiles as $path) {
                if (str_starts_with(strtolower(basename($path)), $prefix)) {
                    return $path;
                }
            }
        }

        return $sqlFiles[0] ?? null;
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function restoreSqlite(array $config, string $sqlPath): bool
    {
        $database = $config['database'] ?? null;
        if (! is_string($database) || $database === '') {
            $this->error('SQLite database path missing in config.');

            return false;
        }

        $dbPath = $database;
        if (! str_starts_with($dbPath, DIRECTORY_SEPARATOR) && preg_match('#^[A-Za-z]:[\\\\/]#', $dbPath) !== 1) {
            $dbPath = base_path($dbPath);
        }

        $backup = $dbPath.'.bak-'.date('Y-m-d-His');
        if (is_file($dbPath)) {
            File::copy($dbPath, $backup);
            $this->info('Backed up current DB to: '.$backup);
        }

        File::delete($dbPath);
        File::put($dbPath, '');

        $sql = file_get_contents($sqlPath);
        if ($sql === false) {
            return false;
        }

        if (str_contains($sql, 'unistr(')) {
            $this->info('Normalizing unistr() in dump for SQLite…');
            $sql = $this->stripSqliteUnistrCalls($sql);
            $sqlPath = dirname($sqlPath).DIRECTORY_SEPARATOR.'normalized-'.basename($sqlPath);
            File::put($sqlPath, $sql);
        }

        $sqlite3 = $this->resolveSqlite3Binary();
        if ($sqlite3 !== null) {
            $p = new Process([$sqlite3, $dbPath, '.read', $sqlPath]);
            $p->setTimeout(3600);
            $p->run();
            if ($p->isSuccessful()) {
                return true;
            }
            $this->warn($p->getErrorOutput().$p->getOutput());
        }

        return $this->restoreSqliteViaPdo($dbPath, $sqlPath, $backup);
    }

    /**
     * Dumps may contain Oracle-style unistr('...\u000a...') which SQLite does not support.
     */
    private function stripSqliteUnistrCalls(string $sql): string
    {
        $needle = 'unistr(';
        $out = '';
        $len = strlen($sql);
        $i = 0;

        while ($i < $len) {
            $pos = strpos($sql, $needle, $i);
            if ($pos === false) {
                $out .= substr($sql, $i);

                break;
            }

            $out .= substr($sql, $i, $pos - $i);
            $j = $pos + strlen($needle);
            while ($j < $len && ctype_space($sql[$j])) {
                $j++;
            }

            if ($j >= $len || $sql[$j] !== "'") {
                $out .= $needle;
                $i = $pos + strlen($needle);

                continue;
            }

            $j++;
            $inner = '';
            while ($j < $len) {
                $c = $sql[$j];
                if ($c === '\\' && $j + 1 < $len) {
                    $n = $sql[$j + 1];
                    if ($n === "'" || $n === '\\') {
                        $inner .= $n;
                        $j += 2;

                        continue;
                    }
                    if ($n === 'u' && $j + 5 < $len && ctype_xdigit($sql[$j + 2].$sql[$j + 3].$sql[$j + 4].$sql[$j + 5])) {
                        $code = hexdec(substr($sql, $j + 2, 4));
                        $inner .= mb_chr((int) $code);
                        $j += 6;

                        continue;
                    }
                    $inner .= $c;
                    $j++;

                    continue;
                }
                if ($c === "'") {
                    $j++;
                    while ($j < $len && ctype_space($sql[$j])) {
                        $j++;
                    }
                    if ($j < $len && $sql[$j] === ')') {
                        $j++;
                        $out .= "'".str_replace("'", "''", $inner)."'";
                        $i = $j;

                        continue 2;
                    }

                    $inner .= $c;
                    $j++;

                    continue;
                }
                $inner .= $c;
                $j++;
            }

            $out .= $needle;
            $i = $pos + strlen($needle);
        }

        return $out;
    }

    private function restoreSqliteViaPdo(string $dbPath, string $sqlPath, string $backupPath): bool
    {
        $this->warn('Using PDO fallback (slow). Install sqlite3 CLI for faster restores.');

        $sql = file_get_contents($sqlPath);
        if ($sql === false) {
            return false;
        }

        try {
            $pdo = new \PDO('sqlite:'.$dbPath);
            $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            $pdo->exec('PRAGMA foreign_keys = OFF;');
            $pdo->exec($sql);
            $pdo->exec('PRAGMA foreign_keys = ON;');

            return true;
        } catch (\Throwable $e) {
            $this->error('PDO import failed: '.$e->getMessage());
            if (is_file($backupPath)) {
                File::copy($backupPath, $dbPath);
                $this->warn('Restored previous DB from .bak file.');
            }

            return false;
        }
    }

    private function resolveSqlite3Binary(): ?string
    {
        $candidates = [];

        $herdBin = getenv('HERD_BIN') ?: 'C:\\Program Files\\Herd\\resources\\bin';
        if (is_string($herdBin) && is_file($herdBin.'\\sqlite3.exe')) {
            $candidates[] = $herdBin.'\\sqlite3.exe';
        }

        $localApp = getenv('LOCALAPPDATA');
        if (is_string($localApp) && is_file($localApp.'\\Herd\\bin\\sqlite3.exe')) {
            $candidates[] = $localApp.'\\Herd\\bin\\sqlite3.exe';
        }

        $candidates[] = 'sqlite3';

        foreach ($candidates as $bin) {
            $p = new Process([$bin, '-version']);
            $p->run();
            if ($p->isSuccessful()) {
                return $bin;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function restoreMysql(array $config, string $sqlPath): bool
    {
        $host = $config['host'] ?? '127.0.0.1';
        $port = (string) ($config['port'] ?? 3306);
        $database = $config['database'] ?? '';
        $user = $config['username'] ?? 'root';
        $password = (string) ($config['password'] ?? '');

        $mysql = $this->resolveMysqlBinary();
        if ($mysql === null) {
            $this->error('mysql client not in PATH. Import the dump manually.');

            return false;
        }

        $sql = file_get_contents($sqlPath);
        if ($sql === false) {
            return false;
        }

        $env = $_ENV;
        $env['MYSQL_PWD'] = $password;

        $p = new Process(
            [$mysql, '-h', $host, '-P', $port, '-u', $user, $database],
            null,
            $env,
            $sql
        );
        $p->setTimeout(3600);
        $p->run();

        if (! $p->isSuccessful()) {
            $this->error($p->getErrorOutput().$p->getOutput());

            return false;
        }

        return true;
    }

    private function resolveMysqlBinary(): ?string
    {
        foreach (['mysql', 'mariadb'] as $bin) {
            $p = new Process([$bin, '--version']);
            $p->run();
            if ($p->isSuccessful()) {
                return $bin;
            }
        }

        return null;
    }
}
