<?php

namespace App\Services;

use App\Models\Branch;

class SaleNumberGenerator
{
    public function forNextId(Branch $branch, int $id): string
    {
        return sprintf('VTA-%s-%06d', $branch->code, $id);
    }
}
