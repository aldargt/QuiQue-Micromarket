<?php

namespace Tests\Feature;

use App\Enums\InventoryMovementType;
use App\Enums\MeasurementUnit;
use App\Enums\RoleSlug;
use App\Models\Branch;
use App\Models\Category;
use App\Models\PaymentDetail;
use App\Models\Product;
use App\Models\Role;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use App\Services\PosCartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class PointOfSaleTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    private Category $category;

    private Role $administratorRole;

    private Role $cashierRole;

    protected function setUp(): void
    {
        parent::setUp();
        $this->branch = Branch::factory()->create(['code' => 'PRINCIPAL']);
        $this->category = Category::factory()->create(['branch_id' => $this->branch]);
        $this->administratorRole = Role::factory()->create(['slug' => RoleSlug::Administrator->value]);
        $this->cashierRole = Role::factory()->create(['slug' => RoleSlug::Cashier->value]);
    }

    public function test_roles_can_access_pos_and_history_while_inactive_user_cannot(): void
    {
        foreach ([$this->administrator(), $this->cashier()] as $user) {
            $this->actingAs($user)->get(route('pos.index'))->assertOk()->assertSee('Punto de venta');
            $this->actingAs($user)->get(route('sales.index'))->assertOk();
        }
        $this->actingAs($this->cashier(['is_active' => false]))->get(route('pos.index'))->assertRedirect(route('login'));
    }

    public function test_pos_exposes_add_modify_and_remove_cart_controls(): void
    {
        $this->actingAs($this->cashier())->get(route('pos.index'))
            ->assertOk()
            ->assertSee('add(product)', false)
            ->assertSee('x-model="item.quantity"', false)
            ->assertSee('remove(index)', false)
            ->assertSee('aria-label="Editar producto"', false)
            ->assertSee('aria-label="Eliminar producto del carrito"', false);
    }

    public function test_clear_cart_uses_integrated_modal_instead_of_native_confirmation(): void
    {
        $this->actingAs($this->cashier())->get(route('pos.index'))
            ->assertOk()
            ->assertSee('role="dialog"', false)
            ->assertSee('¿Vaciar carrito?')
            ->assertSee('Esto no realizará ninguna venta ni modificará el inventario.')
            ->assertSee('openClearModal()', false)
            ->assertDontSee('window.confirm', false);
    }

    public function test_cart_persists_across_modules_and_can_be_cleared_without_side_effects(): void
    {
        $user = $this->cashier();
        $product = $this->product();
        $this->actingAs($user)->putJson(route('pos.cart.update', $product), ['quantity' => '5'])->assertOk();

        foreach (['products.index', 'inventory.index', 'categories.index', 'sales.index'] as $route) {
            $this->get(route($route))->assertOk();
            $this->get(route('pos.index'))->assertOk()->assertSee($product->name);
            $this->assertSame('5', app(PosCartService::class)->items($user)[0]['quantity']);
        }

        $this->deleteJson(route('pos.cart.clear'))->assertNoContent();
        $this->get(route('pos.index'))->assertDontSee($product->name);
        $this->assertSame('10.000', $product->fresh()->stock);
        $this->assertDatabaseCount('sales', 0);
        $this->assertDatabaseCount('inventory_movements', 0);
    }

    public function test_edit_link_returns_to_official_form_and_cart_survives_product_edit(): void
    {
        $user = $this->cashier();
        $product = $this->product(['name' => 'Empanada', 'sale_price' => '5.00']);
        $this->actingAs($user)->putJson(route('pos.cart.update', $product), ['quantity' => '2']);
        $this->get(route('pos.index'))->assertSee('edit?return=pos', false)->assertSee('aria-label="Editar producto"', false);
        $this->get(route('products.edit', ['product' => $product, 'return' => 'pos']))->assertOk()->assertSee('Volver al punto de venta');
        $this->put(route('products.update', $product), [...$this->productData(), 'sale_price' => '6.00', 'return_to' => 'pos'])->assertRedirect(route('pos.index'));
        $this->get(route('pos.index'))->assertSee('Empanada')->assertSee('5.00')->assertSee('6.00');
        $this->assertTrue(app(PosCartService::class)->items($user)[0]['price_changed']);
    }

    public function test_stale_price_must_be_acknowledged_before_checkout(): void
    {
        $user = $this->cashier();
        $product = $this->product(['sale_price' => '5.00']);
        $this->actingAs($user)->putJson(route('pos.cart.update', $product), ['quantity' => '2']);
        $product->update(['sale_price' => '6.00']);

        $staleItem = app(PosCartService::class)->items($user)[0];
        $this->assertTrue($staleItem['price_changed']);
        $this->assertSame('5.00', $staleItem['observed_price']);
        $this->assertSame('6.00', $staleItem['price']);
        $this->assertSame(10.0, (float) $staleItem['observed_price'] * (float) $staleItem['quantity']);
        $this->get(route('pos.index'))
            ->assertSee('effectivePrice(item)', false)
            ->assertSee('item.price_changed ? item.observed_price : item.price', false)
            ->assertSee('i.error || i.price_changed || !i.available', false);

        $this->post(route('pos.sales.store'), $this->requestData($product, ['cash_received' => '20']))->assertSessionHasErrors('items');
        $this->assertDatabaseCount('sales', 0);
        $this->putJson(route('pos.cart.update', $product), ['quantity' => '2', 'acknowledge_price' => true])->assertOk()->assertJson(['price_changed' => false, 'observed_price' => '6.00', 'price' => '6.00']);
        $updatedItem = app(PosCartService::class)->items($user)[0];
        $this->assertSame(12.0, (float) $updatedItem['price'] * (float) $updatedItem['quantity']);
        $this->post(route('pos.sales.store'), $this->requestData($product, ['cash_received' => '20']))->assertSessionHasNoErrors();
        $this->assertSame('12.00', Sale::firstOrFail()->total);
    }

    public function test_search_uses_name_barcode_and_internal_code_and_excludes_unavailable_products(): void
    {
        $barcode = $this->product(['name' => 'Café especial', 'barcode' => '7791234567890', 'internal_code' => null]);
        $internal = $this->product(['name' => 'Pan casero', 'barcode' => null, 'internal_code' => 'PRD-ABCDEFGHIJKL']);
        $this->product(['name' => 'Producto inactivo', 'is_active' => false]);
        $this->product(['name' => 'Producto agotado', 'stock' => '0.000']);
        $user = $this->cashier();
        $this->actingAs($user)->getJson(route('pos.products.search', ['search' => 'Café']))->assertJsonFragment(['id' => $barcode->id]);
        $this->actingAs($user)->getJson(route('pos.products.search', ['search' => '7791234567890']))->assertJsonFragment(['id' => $barcode->id]);
        $this->actingAs($user)->getJson(route('pos.products.search', ['search' => 'PRD-ABCDEFGHIJKL']))->assertJsonFragment(['id' => $internal->id]);
        $this->actingAs($user)->getJson(route('pos.products.search', ['search' => 'Producto']))->assertJsonMissing(['name' => 'Producto inactivo'])->assertJsonMissing(['name' => 'Producto agotado']);
    }

    public function test_cash_sale_recalculates_price_and_creates_all_related_records(): void
    {
        $product = $this->product(['name' => 'Leche', 'sale_price' => '12.50']);
        $user = $this->cashier();
        $response = $this->actingAs($user)->post(route('pos.sales.store'), $this->saleData($product, ['cash_received' => '30.00', 'price' => '0.01', 'total' => '0.01']));
        $sale = Sale::query()->firstOrFail();
        $response->assertRedirect(route('sales.show', $sale));
        $this->assertMatchesRegularExpression('/^VTA-PRINCIPAL-\d{6}$/', $sale->sale_number);
        $this->assertSame('25.00', $sale->total);
        $this->assertSame('8.000', $product->fresh()->stock);
        $this->assertDatabaseHas('sale_items', ['sale_id' => $sale->id, 'quantity' => 2, 'unit_price' => 12.50, 'subtotal' => 25]);
        $this->assertDatabaseHas('payment_details', ['sale_id' => $sale->id, 'method' => 'cash', 'amount' => 25, 'received_amount' => 30, 'change_amount' => 5]);
        $this->assertDatabaseHas('inventory_movements', ['sale_id' => $sale->id, 'type' => InventoryMovementType::Exit->value, 'stock_before' => 10, 'stock_after' => 8]);
        $product->update(['sale_price' => '99.00', 'name' => 'Leche nueva']);
        $this->assertSame('12.50', SaleItem::firstOrFail()->unit_price);
        $this->assertSame('Leche', SaleItem::firstOrFail()->product_name);
        $this->assertSame([], app(PosCartService::class)->items($user));
    }

    public function test_qr_and_mixed_payments_are_recorded(): void
    {
        $user = $this->administrator();
        $this->actingAs($user)->post(route('pos.sales.store'), $this->saleData($this->product(), ['payment_type' => 'qr', 'cash_received' => null]))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('payment_details', ['sale_id' => 1, 'method' => 'qr', 'amount' => 20]);
        $this->actingAs($user)->post(route('pos.sales.store'), $this->saleData($this->product(), ['payment_type' => 'mixed', 'cash_received' => null, 'cash_amount' => '8.00', 'qr_amount' => '12.00']))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('payment_details', ['sale_id' => 2, 'method' => 'cash', 'amount' => 8]);
        $this->assertDatabaseHas('payment_details', ['sale_id' => 2, 'method' => 'qr', 'amount' => 12]);
    }

    public function test_invalid_payments_are_rejected_without_partial_records(): void
    {
        foreach ([
            ['payment_type' => 'cash', 'cash_received' => '19.99'],
            ['payment_type' => 'mixed', 'cash_received' => null, 'cash_amount' => '5', 'qr_amount' => '14'],
            ['payment_type' => 'mixed', 'cash_received' => null, 'cash_amount' => '10', 'qr_amount' => '11'],
        ] as $payment) {
            $this->actingAs($this->cashier())->post(route('pos.sales.store'), $this->saleData($this->product(), $payment))->assertSessionHasErrors();
        }
        $this->assertDatabaseCount('sales', 0);
        $this->assertDatabaseCount('payment_details', 0);
    }

    public function test_product_availability_and_branch_are_enforced_in_backend(): void
    {
        foreach ([$this->product(['is_active' => false]), $this->product(['stock' => '0.000']), $this->product(['stock' => '1.000'])] as $product) {
            $this->actingAs($this->cashier())->post(route('pos.sales.store'), $this->saleData($product))->assertSessionHasErrors();
        }
        $other = Branch::factory()->create();
        $foreign = Product::factory()->create(['branch_id' => $other, 'category_id' => Category::factory()->create(['branch_id' => $other]), 'stock' => '10.000']);
        $this->actingAs($this->cashier())->post(route('pos.sales.store'), $this->saleData($foreign))->assertSessionHasErrors('items');
        $this->assertDatabaseCount('sales', 0);
    }

    public function test_decimal_quantity_is_allowed_for_weight_but_units_are_integer_only(): void
    {
        $weighted = $this->product(['unit' => MeasurementUnit::Kilogram, 'sale_price' => '20.00']);
        $this->actingAs($this->cashier())->post(route('pos.sales.store'), $this->saleData($weighted, ['items' => [['product_id' => $weighted->id, 'quantity' => '0.500']], 'cash_received' => '10.00']))->assertSessionHasNoErrors();
        $this->assertSame('9.500', $weighted->fresh()->stock);
        $unit = $this->product(['unit' => MeasurementUnit::Unit]);
        $this->actingAs($this->cashier())->post(route('pos.sales.store'), $this->saleData($unit, ['items' => [['product_id' => $unit->id, 'quantity' => '0.500']]]))->assertSessionHasErrors('items');
    }

    public function test_quantity_precision_rules_cover_integer_and_decimal_examples(): void
    {
        $user = $this->cashier();
        foreach (['1', '5'] as $quantity) {
            $product = $this->product(['unit' => MeasurementUnit::Unit]);
            $this->actingAs($user)->post(route('pos.sales.store'), $this->saleData($product, ['items' => [['product_id' => $product->id, 'quantity' => $quantity]]]))->assertSessionHasNoErrors();
        }
        foreach (['1.001', '2.5'] as $quantity) {
            $product = $this->product(['unit' => MeasurementUnit::Unit]);
            $this->actingAs($user)->post(route('pos.sales.store'), $this->saleData($product, ['items' => [['product_id' => $product->id, 'quantity' => $quantity]]]))->assertSessionHasErrors('items');
        }
        $kilogram = $this->product(['unit' => MeasurementUnit::Kilogram]);
        $this->actingAs($user)->post(route('pos.sales.store'), $this->saleData($kilogram, ['items' => [['product_id' => $kilogram->id, 'quantity' => '0.123']]]))->assertSessionHasNoErrors();
        $invalid = $this->product(['unit' => MeasurementUnit::Kilogram]);
        $this->actingAs($user)->post(route('pos.sales.store'), $this->saleData($invalid, ['items' => [['product_id' => $invalid->id, 'quantity' => '0.1234']]]))->assertSessionHasErrors('items.0.quantity');
    }

    public function test_failure_rolls_back_every_record_and_stock(): void
    {
        $product = $this->product();
        PaymentDetail::creating(fn () => throw new RuntimeException('Fallo simulado'));
        $this->withoutExceptionHandling();
        try {
            $this->actingAs($this->cashier())->post(route('pos.sales.store'), $this->saleData($product));
            $this->fail('La venta debía fallar.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Fallo simulado', $exception->getMessage());
        }
        $this->assertSame('10.000', $product->fresh()->stock);
        foreach (['sales', 'sale_items', 'payment_details', 'inventory_movements'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
    }

    public function test_history_and_detail_are_isolated_by_branch(): void
    {
        $user = $this->cashier();
        $this->actingAs($user)->post(route('pos.sales.store'), $this->saleData($this->product()));
        $own = Sale::firstOrFail();
        $other = Branch::factory()->create(['code' => 'OTRA']);
        $foreignUser = User::factory()->create(['role_id' => $this->cashierRole, 'branch_id' => $other]);
        $foreign = Sale::factory()->create(['branch_id' => $other, 'user_id' => $foreignUser, 'sale_number' => 'VTA-OTRA-000001']);
        $this->actingAs($user)->get(route('sales.index'))->assertSee($own->sale_number)->assertDontSee($foreign->sale_number);
        $this->actingAs($user)->get(route('sales.show', $own))->assertOk();
        $this->actingAs($user)->get(route('sales.show', $foreign))->assertForbidden();
    }

    private function saleData(Product $product, array $overrides = []): array
    {
        $data = $this->requestData($product, $overrides);
        $cart = collect($data['items'])->mapWithKeys(fn ($item) => [$item['product_id'] => ['quantity' => $item['quantity'], 'observed_price' => Product::find($item['product_id'])->sale_price]])->all();
        session(['pos.cart.'.auth()->id() => $cart]);

        return $data;
    }

    private function requestData(Product $product, array $overrides = []): array
    {
        return ['items' => [['product_id' => $product->id, 'quantity' => '2.000']], 'payment_type' => 'cash', 'cash_received' => '50.00', 'cash_amount' => null, 'qr_amount' => null, ...$overrides];
    }

    private function productData(): array
    {
        return ['name' => 'Empanada', 'barcode' => null, 'category_id' => $this->category->id, 'unit' => MeasurementUnit::Unit->value, 'purchase_price' => '3.00', 'sale_price' => '5.00', 'minimum_stock' => '1', 'expires_at' => null];
    }

    private function product(array $attributes = []): Product
    {
        return Product::factory()->create(['branch_id' => $this->branch, 'category_id' => $this->category, 'stock' => '10.000', 'sale_price' => '10.00', ...$attributes]);
    }

    private function administrator(array $attributes = []): User
    {
        return User::factory()->create(['role_id' => $this->administratorRole, 'branch_id' => $this->branch, ...$attributes]);
    }

    private function cashier(array $attributes = []): User
    {
        return User::factory()->create(['role_id' => $this->cashierRole, 'branch_id' => $this->branch, ...$attributes]);
    }
}
