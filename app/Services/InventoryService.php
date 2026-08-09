<?php

namespace App\Services;

use App\Enums\InventoryMovementType;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryService
{
    public function record(
        User $user,
        Product $product,
        InventoryMovementType $type,
        string $quantity,
        string $reason,
        ?string $observation = null,
        ?Sale $sale = null,
    ): InventoryMovement {
        return DB::transaction(function () use ($user, $product, $type, $quantity, $reason, $observation, $sale) {
            $lockedProduct = Product::query()->whereKey($product->id)->lockForUpdate()->firstOrFail();

            if ($user->branch_id === null || $user->branch_id !== $lockedProduct->branch_id) {
                throw new AuthorizationException('No puede modificar inventario de otra sucursal.');
            }

            if (! $lockedProduct->is_active) {
                throw ValidationException::withMessages([
                    'product' => 'No se pueden registrar movimientos sobre un producto inactivo.',
                ]);
            }

            if ($sale !== null && ($sale->branch_id !== $lockedProduct->branch_id || $sale->user_id !== $user->id)) {
                throw new AuthorizationException('La venta no pertenece al usuario y sucursal indicados.');
            }

            $stockBefore = $lockedProduct->stock;
            $stockAfter = $type->increasesStock()
                ? bcadd($stockBefore, $quantity, 3)
                : bcsub($stockBefore, $quantity, 3);

            if (bccomp($stockAfter, '0', 3) === -1) {
                throw ValidationException::withMessages([
                    'quantity' => "Stock insuficiente. Disponible: {$stockBefore}.",
                ]);
            }

            $lockedProduct->forceFill(['stock' => $stockAfter])->save();

            return InventoryMovement::query()->create([
                'branch_id' => $lockedProduct->branch_id,
                'product_id' => $lockedProduct->id,
                'user_id' => $user->id,
                'sale_id' => $sale?->id,
                'type' => $type,
                'quantity' => $quantity,
                'stock_before' => $stockBefore,
                'stock_after' => $stockAfter,
                'reason' => $reason,
                'observation' => $observation,
            ]);
        });
    }
}
