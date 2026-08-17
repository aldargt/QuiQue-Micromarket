<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class AuditService
{
    /** @param array<string, mixed>|null $oldValues
     * @param  array<string, mixed>|null  $newValues
     */
    public function record(User $user, string $action, Model $record, ?array $oldValues = null, ?array $newValues = null): AuditLog
    {
        return AuditLog::query()->create([
            'branch_id' => $user->branch_id,
            'user_id' => $user->id,
            'action' => $action,
            'auditable_type' => $record::class,
            'auditable_id' => $record->getKey(),
            'old_values' => $oldValues,
            'new_values' => $newValues,
        ]);
    }
}
