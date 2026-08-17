<?php

namespace App\Policies;

use App\Enums\RoleSlug;
use App\Models\User;

class AuditLogPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->branch_id !== null && $user->hasAnyRole([RoleSlug::Administrator->value]);
    }
}
