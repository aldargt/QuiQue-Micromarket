<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Str;
use RuntimeException;

class ProductCodeGenerator
{
    public function generate(): string
    {
        for ($attempt = 0; $attempt < 10; $attempt++) {
            $code = 'PRD-'.Str::upper(Str::random(12));

            if (! Product::query()->where('internal_code', $code)->exists()) {
                return $code;
            }
        }

        throw new RuntimeException('No se pudo generar un código interno único.');
    }
}
