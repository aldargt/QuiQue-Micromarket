<?php

namespace App\Policies;

use App\Enums\RoleSlug;
use App\Models\Product;
use App\Models\User;

class InventoryMovementPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->branch_id !== null && $user->hasAnyRole([
            RoleSlug::Administrator->value,
            RoleSlug::Cashier->value,
        ]);
    }

    public function create(User $user, Product $product): bool
    {
        return $user->branch_id !== null
            && $user->branch_id === $product->branch_id
            && $product->is_active
            && $user->hasAnyRole([
                RoleSlug::Administrator->value,
                RoleSlug::Cashier->value,
            ]);
    }
}
