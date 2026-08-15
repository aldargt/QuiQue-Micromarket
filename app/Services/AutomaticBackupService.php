<?php

namespace App\Services;

use App\Data\BackupResult;
use App\Models\BackupSetting;
use Illuminate\Support\Facades\DB;

class AutomaticBackupService
{
    public function __construct(private BackupManager $manager) {}

    public function isDue(): bool
    {
        if (! config('backup.automatic_enabled')) {
            return false;
        }
        $setting = BackupSetting::current();
        $successDue = $setting->last_successful_backup_at === null || $setting->last_successful_backup_at->addDays((int) config('backup.automatic_interval_days'))->isPast();
        $retryReady = $setting->last_automatic_attempt_at === null || $setting->last_automatic_attempt_at->addMinutes((int) config('backup.automatic_retry_minutes'))->isPast();

        return $successDue && $retryReady;
    }

    public function runIfDue(): ?BackupResult
    {
        $reserved = DB::transaction(function () {
            $setting = BackupSetting::query()->whereKey(1)->lockForUpdate()->first() ?? BackupSetting::query()->create(['id' => 1]);
            $successDue = $setting->last_successful_backup_at === null || $setting->last_successful_backup_at->addDays((int) config('backup.automatic_interval_days'))->isPast();
            $retryReady = $setting->last_automatic_attempt_at === null || $setting->last_automatic_attempt_at->addMinutes((int) config('backup.automatic_retry_minutes'))->isPast();
            if (! config('backup.automatic_enabled') || ! $successDue || ! $retryReady) {
                return false;
            }
            $setting->update(['last_automatic_attempt_at' => now()]);

            return true;
        });

        return $reserved ? $this->manager->run() : null;
    }
}
