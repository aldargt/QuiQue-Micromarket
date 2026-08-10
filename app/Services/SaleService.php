<?php

namespace App\Services;

use App\Enums\InventoryMovementType;
use App\Enums\MeasurementUnit;
use App\Enums\PaymentMethod;
use App\Enums\SaleStatus;
use App\Models\Branch;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaleService
{
    public function __construct(private InventoryService $inventory, private SaleNumberGenerator $numbers) {}

    /** @param array<int, array{product_id:int, quantity:string, expected_price?:string}> $items */
    public function confirm(User $user, array $items, string $paymentType, ?string $cashReceived, ?string $cashAmount, ?string $qrAmount): Sale
    {
        return DB::transaction(function () use ($user, $items, $paymentType, $cashReceived, $cashAmount, $qrAmount) {
            if ($user->branch_id === null) {
                throw new AuthorizationException('El usuario no tiene una sucursal asignada.');
            }
            $branch = Branch::query()->whereKey($user->branch_id)->where('is_active', true)->lockForUpdate()->first();
            if ($branch === null) {
                throw new AuthorizationException('La sucursal no está disponible.');
            }

            $quantities = collect($items)->mapWithKeys(fn ($item) => [(int) $item['product_id'] => (string) $item['quantity']]);
            $expectedPrices = collect($items)->mapWithKeys(fn ($item) => [(int) $item['product_id'] => $item['expected_price'] ?? null]);
            $products = Product::query()->whereIn('id', $quantities->keys())->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            if ($products->count() !== $quantities->count()) {
                throw ValidationException::withMessages(['items' => 'Uno o más productos ya no existen.']);
            }

            $lines = [];
            $total = '0.00';
            foreach ($quantities as $productId => $quantity) {
                $product = $products->get($productId);
                if ($product->branch_id !== $user->branch_id) {
                    throw new AuthorizationException('No puede vender productos de otra sucursal.');
                }
                if (! $product->is_active) {
                    throw ValidationException::withMessages(['items' => "{$product->name} está inactivo y no puede venderse."]);
                }
                $expectedPrice = $expectedPrices->get($productId);
                if ($expectedPrice !== null && bccomp((string) $expectedPrice, $product->sale_price, 2) !== 0) {
                    throw ValidationException::withMessages(['items' => "El precio de \"{$product->name}\" cambió nuevamente. Actualice el carrito antes de confirmar."]);
                }
                if ($product->unit === MeasurementUnit::Unit && bccomp($quantity, (string) (int) $quantity, 3) !== 0) {
                    throw ValidationException::withMessages(['items' => "{$product->name} se vende por unidad y requiere una cantidad entera."]);
                }
                if (bccomp($product->stock, $quantity, 3) === -1) {
                    throw ValidationException::withMessages(['items' => "Stock insuficiente para {$product->name}. Disponible: {$product->stock}."]);
                }
                $subtotal = bcadd(bcmul($quantity, $product->sale_price, 3), '0.005', 2);
                $total = bcadd($total, $subtotal, 2);
                $lines[] = compact('product', 'quantity', 'subtotal');
            }

            $payments = $this->payments($paymentType, $total, $cashReceived, $cashAmount, $qrAmount);
            $sale = Sale::query()->create(['branch_id' => $branch->id, 'user_id' => $user->id, 'sale_number' => 'PENDING-'.bin2hex(random_bytes(6)), 'subtotal' => $total, 'total' => $total, 'status' => SaleStatus::Confirmed, 'confirmed_at' => now()]);
            $sale->update(['sale_number' => $this->numbers->forNextId($branch, $sale->id)]);

            foreach ($lines as $line) {
                $sale->items()->create(['product_id' => $line['product']->id, 'product_name' => $line['product']->name, 'unit' => $line['product']->unit, 'quantity' => $line['quantity'], 'unit_price' => $line['product']->sale_price, 'subtotal' => $line['subtotal']]);
                $this->inventory->record($user, $line['product'], InventoryMovementType::Exit, $line['quantity'], 'Venta '.$sale->sale_number, null, $sale);
            }
            foreach ($payments as $payment) {
                $sale->payments()->create($payment);
            }

            return $sale->load(['items.product', 'payments', 'user']);
        });
    }

    /** @return array<int, array<string, mixed>> */
    private function payments(string $type, string $total, ?string $received, ?string $cash, ?string $qr): array
    {
        if ($type === 'cash') {
            if ($received === null || bccomp($received, $total, 2) === -1) {
                throw ValidationException::withMessages(['cash_received' => 'El efectivo recibido no alcanza para cubrir el total.']);
            }

            return [['method' => PaymentMethod::Cash, 'amount' => $total, 'received_amount' => $received, 'change_amount' => bcsub($received, $total, 2)]];
        }
        if ($type === 'qr') {
            return [['method' => PaymentMethod::Qr, 'amount' => $total, 'received_amount' => null, 'change_amount' => null]];
        }

        $cash ??= '0';
        $qr ??= '0';
        if (bccomp($cash, '0', 2) !== 1 || bccomp($qr, '0', 2) !== 1 || bccomp(bcadd($cash, $qr, 2), $total, 2) !== 0) {
            throw ValidationException::withMessages(['payment' => 'En un pago mixto, efectivo y QR deben ser mayores que cero y sumar exactamente el total.']);
        }

        return [
            ['method' => PaymentMethod::Cash, 'amount' => $cash, 'received_amount' => $cash, 'change_amount' => '0.00'],
            ['method' => PaymentMethod::Qr, 'amount' => $qr, 'received_amount' => null, 'change_amount' => null],
        ];
    }
}
