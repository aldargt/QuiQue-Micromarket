<?php

namespace App\Services;

use App\Enums\MeasurementUnit;
use App\Models\Product;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class PosCartService
{
    /** @return array<int, array<string, mixed>> */
    public function items(User $user): array
    {
        $stored = collect(session($this->key($user), []));
        if ($stored->isEmpty()) {
            return [];
        }

        $products = Product::query()->where('branch_id', $user->branch_id)->whereIn('id', $stored->keys())->get()->keyBy('id');
        $items = $stored->map(function (array $line, int|string $productId) use ($products) {
            $product = $products->get((int) $productId);
            if ($product === null) {
                return null;
            }
            $observedPrice = (string) $line['observed_price'];

            return [
                'id' => $product->id, 'name' => $product->name, 'code' => $product->displayCode(),
                'unit' => $product->unit->label(), 'is_unit' => $product->unit === MeasurementUnit::Unit,
                'price' => $product->sale_price, 'observed_price' => $observedPrice,
                'price_changed' => bccomp($observedPrice, $product->sale_price, 2) !== 0,
                'stock' => $product->stock, 'quantity' => (string) $line['quantity'],
                'available' => $product->is_active && bccomp($product->stock, '0', 3) === 1,
            ];
        })->filter()->values()->all();

        return $items;
    }

    /** @return array<string, mixed> */
    public function put(User $user, Product $product, string $quantity, bool $acknowledgePrice = false): array
    {
        $this->ensureProduct($user, $product, $quantity);
        $cart = session($this->key($user), []);
        $existing = $cart[$product->id] ?? null;
        $cart[$product->id] = [
            'quantity' => $quantity,
            'observed_price' => $existing !== null && ! $acknowledgePrice ? $existing['observed_price'] : $product->sale_price,
        ];
        session([$this->key($user) => $cart]);

        return collect($this->items($user))->firstWhere('id', $product->id);
    }

    public function remove(User $user, Product $product): void
    {
        $cart = session($this->key($user), []);
        unset($cart[$product->id]);
        session([$this->key($user) => $cart]);
    }

    public function clear(User $user): void
    {
        session()->forget($this->key($user));
    }

    /** @param array<int, array{product_id:int, quantity:string}> $submitted
     * @return array<int, array{product_id:int, quantity:string, expected_price:string}>
     */
    public function checkoutItems(User $user, array $submitted): array
    {
        $storedItems = collect($this->items($user));
        $submittedItems = collect($submitted)->mapWithKeys(fn ($line) => [(int) $line['product_id'] => (string) $line['quantity']]);
        if ($storedItems->count() !== $submittedItems->count()) {
            throw ValidationException::withMessages(['items' => 'El carrito cambió. Revise sus productos antes de confirmar.']);
        }

        foreach ($storedItems as $item) {
            if (! $item['available']) {
                throw ValidationException::withMessages(['items' => "{$item['name']} ya no está disponible para la venta."]);
            }
            if ($item['price_changed']) {
                throw ValidationException::withMessages(['items' => "El precio de \"{$item['name']}\" cambió de Bs {$item['observed_price']} a Bs {$item['price']}. Actualice el carrito antes de confirmar."]);
            }
            if (! $submittedItems->has($item['id']) || bccomp($submittedItems->get($item['id']), $item['quantity'], 3) !== 0) {
                throw ValidationException::withMessages(['items' => 'El carrito cambió. Revise las cantidades antes de confirmar.']);
            }
        }

        return $storedItems->map(fn ($item) => ['product_id' => $item['id'], 'quantity' => $item['quantity'], 'expected_price' => $item['observed_price']])->all();
    }

    private function ensureProduct(User $user, Product $product, string $quantity): void
    {
        if ($user->branch_id !== $product->branch_id || ! $product->is_active || bccomp($product->stock, '0', 3) !== 1) {
            throw ValidationException::withMessages(['product' => 'El producto no está disponible para la venta.']);
        }
        if (bccomp($quantity, '0', 3) !== 1 || bccomp($quantity, '999999999.999', 3) === 1) {
            throw ValidationException::withMessages(['quantity' => 'La cantidad debe ser mayor que cero y tener hasta tres decimales.']);
        }
        if ($product->unit === MeasurementUnit::Unit && bccomp($quantity, (string) (int) $quantity, 3) !== 0) {
            throw ValidationException::withMessages(['quantity' => 'Los productos por unidad requieren una cantidad entera.']);
        }
    }

    private function key(User $user): string
    {
        return 'pos.cart.'.$user->id;
    }
}
