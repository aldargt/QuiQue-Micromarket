<?php

return [
    'mysqldump_path' => env('BACKUP_MYSQLDUMP_PATH', 'mysqldump'),
    'local_path' => env('BACKUP_LOCAL_PATH', 'C:/QuiQueMicromarket/Backups'),
    'process_timeout' => (int) env('BACKUP_PROCESS_TIMEOUT', 600),
    'automatic_enabled' => (bool) env('BACKUP_AUTOMATIC_ENABLED', true),
    'automatic_interval_days' => (int) env('BACKUP_AUTOMATIC_INTERVAL_DAYS', 7),
    'automatic_retry_minutes' => (int) env('BACKUP_AUTOMATIC_RETRY_MINUTES', 60),
];
