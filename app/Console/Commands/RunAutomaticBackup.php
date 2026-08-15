<?php

namespace App\Console\Commands;

use App\Services\AutomaticBackupService;
use Illuminate\Console\Command;

class RunAutomaticBackup extends Command
{
    protected $signature = 'backup:automatic';

    protected $description = 'Genera el backup automático si corresponde por antigüedad';

    public function handle(AutomaticBackupService $backups): int
    {
        $result = $backups->runIfDue();
        if ($result === null) {
            $this->info('No corresponde generar un backup en este momento.');

            return self::SUCCESS;
        }
        $this->line('Resultado del backup: '.$result->status.'.');

        return $result->isSuccessful() ? self::SUCCESS : self::FAILURE;
    }
}
