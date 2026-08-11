<?php

namespace App\Policies;

use App\Enums\RoleSlug;
use App\Models\Sale;
use App\Models\User;

class SalePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->hasAccess($user);
    }

    public function create(User $user): bool
    {
        return $this->hasAccess($user);
    }

    public function export(User $user): bool
    {
        return $user->branch_id !== null && $user->hasAnyRole([RoleSlug::Administrator->value]);
    }

    public function view(User $user, Sale $sale): bool
    {
        return $this->hasAccess($user) && $user->branch_id === $sale->branch_id;
    }

    private function hasAccess(User $user): bool
    {
        return $user->branch_id !== null && $user->hasAnyRole([RoleSlug::Administrator->value, RoleSlug::Cashier->value]);
    }
}
