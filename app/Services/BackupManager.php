<?php

namespace App\Services;

use App\Contracts\DatabaseBackupCreator;
use App\Data\BackupResult;
use App\Models\BackupSetting;
use Illuminate\Support\Facades\Cache;
use Throwable;

class BackupManager
{
    public function __construct(private DatabaseBackupCreator $creator) {}

    public function run(): BackupResult
    {
        $lock = Cache::lock('database-backup-generation', (int) config('backup.process_timeout') + 60);
        if (! $lock->get()) {
            return BackupResult::failed('Ya existe un backup en proceso.');
        }

        try {
            $local = $this->creator->create();
            $completedAt = now();
            BackupSetting::current()->update(['last_successful_backup_at' => $completedAt]);

            return BackupResult::successful($local['filename'], $local['path'], $local['sha256'], $completedAt->toIso8601String());
        } catch (Throwable $exception) {
            report($exception);

            return BackupResult::failed($exception->getMessage());
        } finally {
            $lock->release();
        }
    }
}
