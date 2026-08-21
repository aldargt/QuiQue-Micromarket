<?php

namespace Tests\Feature;

use App\Enums\MeasurementUnit;
use App\Enums\RoleSlug;
use App\Models\Branch;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductPriceHistory;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductManagementTest extends TestCase
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
        $this->category = Category::factory()->create(['branch_id' => $this->branch->id, 'name' => 'Bebidas']);
        $this->administratorRole = Role::factory()->create(['name' => 'Administrador', 'slug' => RoleSlug::Administrator->value]);
        $this->cashierRole = Role::factory()->create(['name' => 'Cajero', 'slug' => RoleSlug::Cashier->value]);
    }

    public function test_administrator_and_cashier_can_access_products(): void
    {
        foreach ([$this->administrator(), $this->cashier()] as $user) {
            $this->actingAs($user)->get(route('products.index'))->assertOk();
            $response = $this->actingAs($user)->get(route('products.create'))
                ->assertOk()
                ->assertSee('step="any"', false)
                ->assertSee('autocomplete="off"', false)
                ->assertSee('select-placeholder', false)
                ->assertSee('Unidad')->assertSee('Kilogramo')->assertSee('Litro')
                ->assertDontSee('Gramo')->assertDontSee('Mililitro');
            $this->assertSame(4, substr_count($response->getContent(), '(obligatorio)'));
        }
    }

    public function test_product_can_be_created_with_barcode_and_protected_fields_are_ignored(): void
    {
        $creator = $this->cashier();
        $otherBranch = Branch::factory()->create();

        $this->actingAs($creator)->post(route('products.store'), [
            ...$this->validData(),
            'name' => '  Leche   Pil 1L  ',
            'barcode' => '7771234567890',
            'branch_id' => $otherBranch->id,
            'internal_code' => 'MANIPULADO',
            'created_by' => $this->administrator()->id,
            'is_active' => false,
        ])->assertRedirect(route('products.index'));

        $product = Product::query()->sole();
        $this->assertSame('Leche Pil 1L', $product->name);
        $this->assertSame('7771234567890', $product->barcode);
        $this->assertNull($product->internal_code);
        $this->assertSame('0.000', $product->stock);
        $this->assertSame($this->branch->id, $product->branch_id);
        $this->assertSame($creator->id, $product->created_by);
        $this->assertTrue($product->is_active);
    }

    public function test_products_without_barcode_receive_unique_stable_internal_codes(): void
    {
        $user = $this->administrator();

        foreach (['Pan de batalla', 'Papa por kilo'] as $name) {
            $this->actingAs($user)->post(route('products.store'), [...$this->validData(), 'name' => $name, 'barcode' => ''])
                ->assertSessionHasNoErrors();
        }

        $products = Product::query()->orderBy('id')->get();
        $this->assertNull($products[0]->barcode);
        $this->assertMatchesRegularExpression('/^PRD-[A-Z0-9]{12}$/', $products[0]->internal_code);
        $this->assertMatchesRegularExpression('/^PRD-[A-Z0-9]{12}$/', $products[1]->internal_code);
        $this->assertNotSame($products[0]->internal_code, $products[1]->internal_code);

        $code = $products[0]->internal_code;
        $this->actingAs($user)->put(route('products.update', $products[0]), [...$this->validData(), 'name' => 'Pan actualizado']);
        $this->assertSame($code, $products[0]->fresh()->internal_code);
    }

    public function test_switching_barcode_state_updates_internal_identifier_exactly(): void
    {
        $product = $this->product(['barcode' => null]);
        $this->assertNotNull($product->internal_code);
        $user = $this->administrator();

        $this->actingAs($user)->put(route('products.update', $product), [
            ...$this->validData(),
            'barcode' => '7771234567890',
        ])->assertSessionHasNoErrors();

        $product->refresh();
        $this->assertSame('7771234567890', $product->barcode);
        $this->assertNull($product->internal_code);

        $this->actingAs($user)->put(route('products.update', $product), [
            ...$this->validData(),
            'barcode' => null,
        ])->assertSessionHasNoErrors();

        $product->refresh();
        $this->assertNull($product->barcode);
        $this->assertMatchesRegularExpression('/^PRD-[A-Z0-9]{12}$/', $product->internal_code);
    }

    public function test_duplicate_barcode_is_rejected_for_active_products_in_same_branch(): void
    {
        $this->product(['barcode' => '7771234567890']);

        $this->actingAs($this->cashier())->post(route('products.store'), [
            ...$this->validData(),
            'barcode' => '7771234567890',
        ])->assertSessionHasErrors('barcode');

        $this->assertDatabaseCount('products', 1);
    }

    public function test_inactive_duplicate_barcode_can_exist_but_cannot_be_reactivated_while_in_use(): void
    {
        $this->product(['barcode' => '7771234567890', 'is_active' => true]);
        $inactive = $this->product(['barcode' => '7771234567890', 'is_active' => false]);

        $this->actingAs($this->administrator())->patch(route('products.toggle', $inactive))
            ->assertSessionHasErrors('barcode');

        $this->assertFalse($inactive->fresh()->is_active);
    }

    public function test_cashier_can_edit_only_prices_and_malicious_structural_fields_do_not_change(): void
    {
        $product = $this->product(['stock' => 12.5]);
        $otherBranch = Branch::factory()->create();

        $this->actingAs($this->cashier())->put(route('products.update', $product), [
            ...$this->validData(),
            'name' => 'Producto actualizado',
            'sale_price' => '25.50',
            'stock' => '999',
            'branch_id' => $otherBranch->id,
            'internal_code' => 'CAMBIO-NO-PERMITIDO',
        ])->assertRedirect(route('products.index'))->assertSessionHas('status');

        $product->refresh();
        $this->assertNotSame('Producto actualizado', $product->name);
        $this->assertSame($this->validData()['purchase_price'], $product->purchase_price);
        $this->assertSame('25.50', $product->sale_price);
        $this->assertSame('12.500', $product->stock);
        $this->assertSame($this->branch->id, $product->branch_id);
        $this->assertNotSame('CAMBIO-NO-PERMITIDO', $product->internal_code);
    }

    public function test_administrator_can_edit_all_current_product_fields(): void
    {
        $product = $this->product();
        $this->actingAs($this->administrator())->put(route('products.update', $product), [
            ...$this->validData(), 'name' => 'Producto administrativo', 'sale_price' => '25.50',
        ])->assertSessionHasNoErrors();

        $this->assertSame('Producto administrativo', $product->fresh()->name);
        $this->assertSame('25.50', $product->fresh()->sale_price);
    }

    public function test_purchase_and_sale_price_changes_share_an_immutable_product_history(): void
    {
        $product = $this->product(['purchase_price' => '2.00', 'sale_price' => '12.00']);
        $user = $this->cashier();
        $time = now()->startOfSecond();
        $this->travelTo($time);

        $this->actingAs($user)->put(route('products.update', $product), [...$this->validData(), 'purchase_price' => '3.00', 'sale_price' => '12.00'])->assertSessionHasNoErrors();
        $this->actingAs($user)->put(route('products.update', $product), [...$this->validData(), 'purchase_price' => '3.00', 'sale_price' => '15.00'])->assertSessionHasNoErrors();
        $this->actingAs($user)->put(route('products.update', $product), [...$this->validData(), 'purchase_price' => '4.00', 'sale_price' => '18.00'])->assertSessionHasNoErrors();
        $history = ProductPriceHistory::query()->oldest()->get();
        $this->assertCount(3, $history);
        $this->assertSame(['2.00', '3.00', '12.00', '12.00'], [$history[0]->old_purchase_price, $history[0]->new_purchase_price, $history[0]->old_price, $history[0]->new_price]);
        $this->assertSame(['3.00', '3.00', '12.00', '15.00'], [$history[1]->old_purchase_price, $history[1]->new_purchase_price, $history[1]->old_price, $history[1]->new_price]);
        $this->assertSame(['3.00', '4.00', '15.00', '18.00'], [$history[2]->old_purchase_price, $history[2]->new_purchase_price, $history[2]->old_price, $history[2]->new_price]);
        $this->assertTrue($history->every(fn ($change) => $change->user_id === $user->id && $change->created_at->equalTo($time)));

        $this->actingAs($user)->put(route('products.update', $product), [...$this->validData(), 'purchase_price' => '4.00', 'sale_price' => '18.00'])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('product_price_history', 3);
        $this->actingAs($user)->get(route('products.edit', $product))->assertSee('Historial de precios')->assertSee('Precio de compra')->assertSee('Precio de venta')->assertSee($user->name);
        $this->delete('/product-price-history/'.$history[0]->id)->assertNotFound();
        $this->put('/product-price-history/'.$history[0]->id)->assertNotFound();
        $this->assertNotNull($history[0]->fresh());
        $this->travelBack();
    }

    public function test_minimum_stock_input_uses_integer_or_decimal_format_by_unit(): void
    {
        $user = $this->administrator();
        $units = $this->product(['unit' => MeasurementUnit::Unit, 'minimum_stock' => '10.000']);
        $kilograms = $this->product(['unit' => MeasurementUnit::Kilogram, 'minimum_stock' => '10.500']);

        $this->actingAs($user)->get(route('products.edit', $units))->assertOk()
            ->assertSee('name="minimum_stock"', false)->assertSee('step="1"', false)->assertSee('value="10"', false)->assertDontSee('value="10.000"', false);
        $this->actingAs($user)->get(route('products.edit', $kilograms))->assertOk()
            ->assertSee('step="0.001"', false)->assertSee('value="10.5"', false);
        $this->actingAs($user)->get(route('products.create'))->assertOk()->assertSee("this.value === 'unit' ? '1' : '0.001'", false);
    }

    public function test_search_and_filters_work_with_name_codes_category_status_and_zero_stock(): void
    {
        $target = $this->product(['name' => 'Leche especial', 'barcode' => '7771234567890', 'internal_code' => null, 'stock' => 0, 'is_active' => false]);
        $internalTarget = $this->product(['name' => 'Pan interno', 'barcode' => null]);
        $this->product(['name' => 'Galletas', 'stock' => 8, 'is_active' => true]);
        $user = $this->administrator();

        foreach (['Leche', '7771234567890'] as $search) {
            $this->actingAs($user)->get(route('products.index', ['search' => $search]))
                ->assertSee('Leche especial')->assertDontSee('Galletas')->assertDontSee('Pan interno');
        }

        $this->actingAs($user)->get(route('products.index', ['search' => $internalTarget->internal_code]))
            ->assertSee('Pan interno')->assertDontSee('Leche especial')->assertDontSee('Galletas');

        $this->actingAs($user)->get(route('products.index', [
            'category' => $this->category->id,
            'status' => 'inactive',
            'stock' => 'zero',
        ]))->assertSee('Leche especial')->assertDontSee('Galletas');
    }

    public function test_product_stock_is_presented_according_to_its_measurement_unit(): void
    {
        $this->product(['name' => 'Producto unitario', 'unit' => MeasurementUnit::Unit, 'stock' => '22.000']);
        $this->product(['name' => 'Producto pesado', 'unit' => MeasurementUnit::Kilogram, 'stock' => '46.500']);
        $this->product(['name' => 'Producto líquido', 'unit' => MeasurementUnit::Liter, 'stock' => '2.500']);

        $this->actingAs($this->cashier())->get(route('products.index'))->assertOk()
            ->assertSee('22 unidades')->assertDontSee('22,000')
            ->assertSee('46,500 kg')->assertSee('2,500 L');
    }

    public function test_valid_category_rules_are_enforced(): void
    {
        $otherBranch = Branch::factory()->create();
        $foreignCategory = Category::factory()->create(['branch_id' => $otherBranch->id]);
        $inactiveCategory = Category::factory()->create(['branch_id' => $this->branch->id, 'is_active' => false]);
        $user = $this->cashier();

        foreach ([$foreignCategory, $inactiveCategory] as $category) {
            $this->actingAs($user)->post(route('products.store'), [
                ...$this->validData(),
                'category_id' => $category->id,
            ])->assertSessionHasErrors('category_id');
        }
    }

    public function test_existing_product_can_keep_inactive_historical_category_but_not_switch_to_one(): void
    {
        $product = $this->product();
        $product->category->update(['is_active' => false]);
        $otherInactive = Category::factory()->create(['branch_id' => $this->branch->id, 'is_active' => false]);
        $user = $this->administrator();

        $this->actingAs($user)->put(route('products.update', $product), $this->validData())
            ->assertSessionHasNoErrors();
        $this->actingAs($user)->put(route('products.update', $product), [
            ...$this->validData(),
            'category_id' => $otherInactive->id,
        ])->assertSessionHasErrors('category_id');
    }

    public function test_invalid_prices_minimum_stock_units_barcode_and_date_are_rejected(): void
    {
        $response = $this->actingAs($this->cashier())->post(route('products.store'), [
            ...$this->validData(),
            'barcode' => 'ABC',
            'unit' => 'box',
            'purchase_price' => '-1',
            'sale_price' => '-2',
            'stock' => '-3',
            'minimum_stock' => '-4',
            'expires_at' => 'fecha-invalida',
        ]);

        $response->assertSessionHasErrors([
            'barcode', 'unit', 'purchase_price', 'sale_price', 'minimum_stock', 'expires_at',
        ]);
    }

    public function test_gram_and_milliliter_are_rejected_as_new_product_units(): void
    {
        foreach (['gram', 'milliliter'] as $unit) {
            $this->actingAs($this->cashier())->post(route('products.store'), [...$this->validData(), 'name' => 'Producto '.$unit, 'unit' => $unit])
                ->assertSessionHasErrors('unit');
        }
        $this->assertDatabaseCount('products', 0);
    }

    public function test_access_and_branch_isolation_are_enforced_and_delete_route_does_not_exist(): void
    {
        $product = $this->product();
        $this->get(route('products.index'))->assertRedirect(route('login'));
        $this->actingAs($this->cashier(['is_active' => false]))->get(route('products.index'))->assertRedirect(route('login'));

        $unsupportedRole = Role::factory()->create(['slug' => 'unsupported']);
        $unsupported = User::factory()->create(['role_id' => $unsupportedRole->id, 'branch_id' => $this->branch->id]);
        $this->actingAs($unsupported)->get(route('products.index'))->assertForbidden();

        $otherBranch = Branch::factory()->create();
        $otherCategory = Category::factory()->create(['branch_id' => $otherBranch->id]);
        $foreignProduct = Product::factory()->create(['branch_id' => $otherBranch->id, 'category_id' => $otherCategory->id]);
        $this->actingAs($this->administrator())->get(route('products.edit', $foreignProduct))->assertForbidden();

        $this->actingAs($this->administrator())->delete('/products/'.$product->id)->assertMethodNotAllowed();
        $this->assertNotNull($product->fresh());
    }

    /** @return array<string, string|int|null> */
    private function validData(): array
    {
        return [
            'name' => 'Producto de prueba',
            'barcode' => null,
            'category_id' => $this->category->id,
            'unit' => MeasurementUnit::Unit->value,
            'purchase_price' => '10.00',
            'sale_price' => '12.50',
            'stock' => '20.000',
            'minimum_stock' => '3.000',
            'expires_at' => '2027-08-14',
        ];
    }

    /** @param array<string, mixed> $attributes */
    private function product(array $attributes = []): Product
    {
        return Product::factory()->create([
            'branch_id' => $this->branch->id,
            'category_id' => $this->category->id,
            ...$attributes,
        ]);
    }

    private function administrator(): User
    {
        return User::factory()->create(['role_id' => $this->administratorRole->id, 'branch_id' => $this->branch->id]);
    }

    /** @param array<string, mixed> $attributes */
    private function cashier(array $attributes = []): User
    {
        return User::factory()->create([
            'role_id' => $this->cashierRole->id,
            'branch_id' => $this->branch->id,
            ...$attributes,
        ]);
    }
}
