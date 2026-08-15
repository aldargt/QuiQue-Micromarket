<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BackupSetting extends Model
{
    protected $fillable = ['id', 'last_successful_backup_at', 'last_automatic_attempt_at'];

    protected function casts(): array
    {
        return ['last_successful_backup_at' => 'datetime', 'last_automatic_attempt_at' => 'datetime'];
    }

    public static function current(): self
    {
        return self::query()->firstOrCreate(['id' => 1]);
    }
}
