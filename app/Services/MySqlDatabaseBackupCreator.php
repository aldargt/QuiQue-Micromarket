<?php

namespace App\Services;

use App\Contracts\DatabaseBackupCreator;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use RuntimeException;
use Throwable;

class MySqlDatabaseBackupCreator implements DatabaseBackupCreator
{
    public function create(): array
    {
        $directory = (string) config('backup.local_path');
        File::ensureDirectoryExists($directory);
        $filename = 'backup_'.now()->format('Y-m-d_H-i-s').'.sql.gz';
        $sqlPath = $directory.DIRECTORY_SEPARATOR.$filename.'.sql.part';
        $gzipPath = $directory.DIRECTORY_SEPARATOR.$filename.'.part';
        $finalPath = $directory.DIRECTORY_SEPARATOR.$filename;
        $defaultsPath = $directory.DIRECTORY_SEPARATOR.'.mysql-'.bin2hex(random_bytes(8)).'.cnf';

        if (File::exists($finalPath)) {
            throw new RuntimeException('Ya existe un backup con el mismo nombre.');
        }

        try {
            File::put($defaultsPath, $this->defaultsFile());
            @chmod($defaultsPath, 0600);
            $result = Process::timeout((int) config('backup.process_timeout'))->run([
                (string) config('backup.mysqldump_path'),
                '--defaults-extra-file='.$defaultsPath,
                '--single-transaction', '--quick', '--routines', '--triggers', '--events', '--hex-blob',
                '--default-character-set=utf8mb4', '--result-file='.$sqlPath,
                (string) config('database.connections.mysql.database'),
            ]);
            if ($result->failed()) {
                throw new RuntimeException('mysqldump finalizó con error: '.$result->errorOutput());
            }
            if (! File::exists($sqlPath) || File::size($sqlPath) < 1) {
                throw new RuntimeException('mysqldump no generó un archivo válido.');
            }

            $this->compress($sqlPath, $gzipPath);
            $this->validateGzip($gzipPath);
            if (! File::move($gzipPath, $finalPath)) {
                throw new RuntimeException('No fue posible publicar el archivo de backup.');
            }

            $sha256 = hash_file('sha256', $finalPath);
            if (! is_string($sha256) || strlen($sha256) !== 64) {
                File::delete($finalPath);
                throw new RuntimeException('No fue posible generar el SHA-256 del backup.');
            }

            return ['path' => $finalPath, 'filename' => $filename, 'sha256' => $sha256];
        } catch (Throwable $exception) {
            File::delete([$sqlPath, $gzipPath]);
            throw $exception;
        } finally {
            File::delete([$defaultsPath, $sqlPath]);
        }
    }

    private function defaultsFile(): string
    {
        $database = config('database.connections.mysql');
        $quote = fn ($value) => '"'.str_replace(['\\', '"'], ['\\\\', '\\"'], (string) $value).'"';

        return implode(PHP_EOL, ['[client]', 'host='.$quote($database['host']), 'port='.(int) $database['port'], 'user='.$quote($database['username']), 'password='.$quote($database['password']), 'protocol=tcp', '']);
    }

    private function compress(string $source, string $destination): void
    {
        $input = fopen($source, 'rb');
        $output = gzopen($destination, 'wb9');
        if ($input === false || $output === false) {
            if (is_resource($input)) {
                fclose($input);
            }
            throw new RuntimeException('No fue posible iniciar la compresión del backup.');
        }
        try {
            while (! feof($input)) {
                $chunk = fread($input, 1024 * 1024);
                if ($chunk === false || gzwrite($output, $chunk) === false) {
                    throw new RuntimeException('La compresión del backup falló.');
                }
            }
        } finally {
            fclose($input);
            gzclose($output);
        }
    }

    private function validateGzip(string $path): void
    {
        if (! File::exists($path) || File::size($path) < 1) {
            throw new RuntimeException('El archivo comprimido está vacío.');
        }
        $stream = gzopen($path, 'rb');
        if ($stream === false) {
            throw new RuntimeException('El archivo comprimido no puede abrirse.');
        }
        $bytes = 0;
        try {
            while (! gzeof($stream)) {
                $chunk = gzread($stream, 1024 * 1024);
                if ($chunk === false) {
                    throw new RuntimeException('El archivo comprimido está dañado.');
                }
                $bytes += strlen($chunk);
            }
        } finally {
            gzclose($stream);
        }
        if ($bytes < 1) {
            throw new RuntimeException('El backup no contiene datos SQL.');
        }
    }
}
