<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Validation\ValidationException;

class ProductBarcodeGuard
{
    public function ensureAvailable(int $branchId, ?string $barcode, ?Product $except = null): void
    {
        if ($barcode === null) {
            return;
        }

        $exists = Product::query()
            ->where('branch_id', $branchId)
            ->where('barcode', $barcode)
            ->where('is_active', true)
            ->when($except, fn ($query) => $query->whereKeyNot($except->getKey()))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'barcode' => 'Ya existe un producto activo con este código de barras en la sucursal.',
            ]);
        }
    }
}
