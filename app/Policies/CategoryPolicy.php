<?php

namespace App\Policies;

use App\Enums\RoleSlug;
use App\Models\Category;
use App\Models\User;

class CategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->hasCategoryAccess($user);
    }

    public function create(User $user): bool
    {
        return $this->hasCategoryAccess($user) && $user->branch_id !== null;
    }

    public function update(User $user, Category $category): bool
    {
        return $this->hasCategoryAccess($user)
            && $user->branch_id !== null
            && $user->branch_id === $category->branch_id;
    }

    private function hasCategoryAccess(User $user): bool
    {
        return $user->hasAnyRole([
            RoleSlug::Administrator->value,
            RoleSlug::Cashier->value,
        ]);
    }
}
