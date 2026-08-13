<?php

namespace App\Policies;

use App\Enums\RoleSlug;
use App\Models\Customer;
use App\Models\User;

class CustomerPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->access($user);
    }

    public function view(User $user, Customer $customer): bool
    {
        return $this->access($user) && $user->branch_id === $customer->branch_id;
    }

    private function access(User $user): bool
    {
        return $user->branch_id !== null && $user->hasAnyRole([RoleSlug::Administrator->value, RoleSlug::Cashier->value]);
    }
}
