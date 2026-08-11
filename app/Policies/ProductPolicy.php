<?php

namespace App\Policies;

use App\Enums\RoleSlug;
use App\Models\Product;
use App\Models\User;

class ProductPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->hasProductAccess($user);
    }

    public function create(User $user): bool
    {
        return $this->hasProductAccess($user) && $user->branch_id !== null;
    }

    public function export(User $user): bool
    {
        return $user->branch_id !== null && $this->hasProductAccess($user);
    }

    public function update(User $user, Product $product): bool
    {
        return $this->hasProductAccess($user)
            && $user->branch_id !== null
            && $user->branch_id === $product->branch_id;
    }

    private function hasProductAccess(User $user): bool
    {
        return $user->hasAnyRole([
            RoleSlug::Administrator->value,
            RoleSlug::Cashier->value,
        ]);
    }
}
