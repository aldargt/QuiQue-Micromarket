<?php

namespace Tests\Feature;

use App\Enums\InventoryMovementType;
use App\Enums\RoleSlug;
use App\Models\Branch;
use App\Models\Category;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use App\Services\InventoryService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class InventoryManagementTest extends TestCase
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
        $this->category = Category::factory()->create(['branch_id' => $this->branch->id]);
        $this->administratorRole = Role::factory()->create(['slug' => RoleSlug::Administrator->value]);
        $this->cashierRole = Role::factory()->create(['slug' => RoleSlug::Cashier->value]);
    }

    public function test_administrator_and_cashier_can_consult_inventory_and_history(): void
    {
        foreach ([$this->administrator(), $this->cashier()] as $user) {
            $this->actingAs($user)->get(route('inventory.index'))->assertOk();
            $this->actingAs($user)->get(route('inventory.movements.index'))->assertOk();
        }
    }

    public function test_administrator_and_cashier_can_register_all_manual_movement_types(): void
    {
        $administratorProduct = $this->product();
        $this->actingAs($this->administrator())->get(route('inventory.movements.create', $administratorProduct))->assertOk();

        $cashier = $this->cashier();
        foreach ([
            [InventoryMovementType::Entry, '0.000'],
            [InventoryMovementType::Exit, '10.000'],
            [InventoryMovementType::PositiveAdjustment, '0.000'],
            [InventoryMovementType::NegativeAdjustment, '10.000'],
        ] as [$type, $stock]) {
            $product = $this->product(['stock' => $stock]);
            $this->actingAs($cashier)->get(route('inventory.movements.create', $product))
                ->assertOk()->assertSee('step="any"', false);
            $this->actingAs($cashier)->post(route('inventory.movements.store', $product), $this->movementData([
                'type' => $type->value,
            ]))->assertSessionHasNoErrors();
            $this->assertDatabaseHas('inventory_movements', [
                'product_id' => $product->id,
                'user_id' => $cashier->id,
                'type' => $type->value,
            ]);
        }
    }

    public function test_entry_increases_stock_and_records_complete_movement(): void
    {
        $product = $this->product(['stock' => '0.000']);
        $user = $this->administrator();

        $this->actingAs($user)->post(route('inventory.movements.store', $product), $this->movementData([
            'type' => InventoryMovementType::Entry->value,
            'quantity' => '100.125',
            'reason' => 'Entrada de mercadería',
            'observation' => 'Compra recibida completa.',
        ]))->assertSessionHasNoErrors();

        $this->assertSame('100.125', $product->fresh()->stock);
        $this->assertDatabaseHas('inventory_movements', [
            'product_id' => $product->id,
            'branch_id' => $this->branch->id,
            'user_id' => $user->id,
            'type' => InventoryMovementType::Entry->value,
            'quantity' => 100.125,
            'stock_before' => 0,
            'stock_after' => 100.125,
            'reason' => 'Entrada de mercadería',
            'observation' => 'Compra recibida completa.',
        ]);
    }

    public function test_exit_decreases_stock_and_cannot_make_it_negative(): void
    {
        $product = $this->product(['stock' => '10.000']);
        $user = $this->administrator();

        $this->actingAs($user)->post(route('inventory.movements.store', $product), $this->movementData([
            'type' => InventoryMovementType::Exit->value,
            'quantity' => '4.250',
        ]))->assertSessionHasNoErrors();
        $this->assertSame('5.750', $product->fresh()->stock);

        $this->actingAs($user)->post(route('inventory.movements.store', $product), $this->movementData([
            'type' => InventoryMovementType::Exit->value,
            'quantity' => '6.000',
        ]))->assertSessionHasErrors('quantity');

        $this->assertSame('5.750', $product->fresh()->stock);
        $this->assertDatabaseCount('inventory_movements', 1);
    }

    public function test_positive_and_negative_adjustments_calculate_stock_correctly(): void
    {
        $product = $this->product(['stock' => '20.000']);
        $user = $this->administrator();

        $this->actingAs($user)->post(route('inventory.movements.store', $product), $this->movementData([
            'type' => InventoryMovementType::PositiveAdjustment->value,
            'quantity' => '5.000',
            'reason' => 'Conteo físico',
        ]))->assertSessionHasNoErrors();
        $this->assertSame('25.000', $product->fresh()->stock);

        $this->actingAs($user)->post(route('inventory.movements.store', $product), $this->movementData([
            'type' => InventoryMovementType::NegativeAdjustment->value,
            'quantity' => '3.000',
            'reason' => 'Producto dañado',
        ]))->assertSessionHasNoErrors();
        $this->assertSame('22.000', $product->fresh()->stock);
        $this->assertDatabaseHas('inventory_movements', [
            'type' => InventoryMovementType::NegativeAdjustment->value,
            'reason' => 'Producto dañado',
            'stock_before' => 25,
            'stock_after' => 22,
        ]);
    }

    public function test_negative_adjustment_cannot_exceed_available_stock(): void
    {
        $product = $this->product(['stock' => '2.000']);

        $this->actingAs($this->administrator())->post(route('inventory.movements.store', $product), $this->movementData([
            'type' => InventoryMovementType::NegativeAdjustment->value,
            'quantity' => '2.001',
        ]))->assertSessionHasErrors('quantity');

        $this->assertSame('2.000', $product->fresh()->stock);
        $this->assertDatabaseCount('inventory_movements', 0);
    }

    public function test_failed_movement_creation_rolls_back_stock_update(): void
    {
        $product = $this->product(['stock' => '5.000']);
        $user = $this->administrator();
        InventoryMovement::creating(fn () => throw new RuntimeException('Fallo simulado'));

        try {
            app(InventoryService::class)->record(
                $user,
                $product,
                InventoryMovementType::Entry,
                '2.000',
                'Prueba transaccional',
            );
            $this->fail('La operación debía fallar.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Fallo simulado', $exception->getMessage());
        }

        $this->assertSame('5.000', $product->fresh()->stock);
        $this->assertDatabaseCount('inventory_movements', 0);
    }

    public function test_inactive_product_rejects_new_movements_but_keeps_history(): void
    {
        $product = $this->product(['stock' => '2.000']);
        $movement = InventoryMovement::factory()->create([
            'branch_id' => $this->branch->id,
            'product_id' => $product->id,
            'user_id' => $this->administrator()->id,
        ]);
        $product->update(['is_active' => false]);

        $this->actingAs($this->administrator())->get(route('inventory.movements.index', ['product' => $product->id]))
            ->assertOk()->assertSee($movement->reason);
        $this->actingAs($this->administrator())->post(route('inventory.movements.store', $product), $this->movementData())
            ->assertForbidden();
        $this->assertNotNull($movement->fresh());
    }

    public function test_access_branch_and_missing_branch_rules_are_enforced(): void
    {
        $product = $this->product();
        $this->get(route('inventory.index'))->assertRedirect(route('login'));
        $this->actingAs($this->cashier(['is_active' => false]))->get(route('inventory.index'))->assertRedirect(route('login'));
        $this->actingAs($this->administrator(['branch_id' => null]))->get(route('inventory.index'))->assertForbidden();

        $otherBranch = Branch::factory()->create();
        $otherCategory = Category::factory()->create(['branch_id' => $otherBranch->id]);
        $foreignProduct = Product::factory()->create(['branch_id' => $otherBranch->id, 'category_id' => $otherCategory->id]);
        $this->actingAs($this->administrator())->get(route('inventory.movements.create', $foreignProduct))->assertForbidden();
        $this->actingAs($this->administrator())->post(route('inventory.movements.store', $foreignProduct), $this->movementData())->assertForbidden();
        $this->assertSame('0.000', $foreignProduct->fresh()->stock);
        $this->assertNotNull($product);
    }

    public function test_quantity_validation_accepts_three_decimals_and_rejects_invalid_values(): void
    {
        $user = $this->administrator();
        $validProduct = $this->product();
        $this->actingAs($user)->post(route('inventory.movements.store', $validProduct), $this->movementData(['quantity' => '1.234']))
            ->assertSessionHasNoErrors();
        $this->assertSame('1.234', $validProduct->fresh()->stock);

        foreach (['0', '-1', '1.2345', 'abc'] as $quantity) {
            $product = $this->product();
            $this->actingAs($user)->post(route('inventory.movements.store', $product), $this->movementData(['quantity' => $quantity]))
                ->assertSessionHasErrors('quantity');
            $this->assertSame('0.000', $product->fresh()->stock);
        }
    }

    public function test_stock_alerts_and_filters_distinguish_zero_low_and_normal(): void
    {
        $zero = $this->product(['name' => 'Producto agotado', 'stock' => '0.000', 'minimum_stock' => '3.000']);
        $low = $this->product(['name' => 'Producto bajo', 'stock' => '2.000', 'minimum_stock' => '3.000']);
        $normal = $this->product(['name' => 'Producto normal', 'stock' => '5.000', 'minimum_stock' => '3.000']);
        $user = $this->cashier();

        $this->assertTrue($zero->hasZeroStock());
        $this->assertTrue($low->hasLowStock());
        $this->assertFalse($normal->hasLowStock());

        $this->actingAs($user)->get(route('inventory.index', ['stock' => 'zero']))
            ->assertSee('Producto agotado')->assertDontSee('Producto bajo')->assertDontSee('Producto normal');
        $this->actingAs($user)->get(route('inventory.index', ['stock' => 'low']))
            ->assertSee('Producto bajo')->assertDontSee('Producto agotado')->assertDontSee('Producto normal');
    }

    public function test_expiration_filter_includes_only_products_expiring_in_next_seven_days(): void
    {
        $this->product(['name' => 'Vence pronto', 'expires_at' => today()->addDays(3)]);
        $this->product(['name' => 'Vence después', 'expires_at' => today()->addDays(10)]);

        $this->actingAs($this->cashier())->get(route('inventory.index', ['expiration' => 'expiring']))
            ->assertSee('Vence pronto')->assertDontSee('Vence después');
    }

    public function test_inventory_timestamps_use_configured_local_timezone(): void
    {
        $localTime = Carbon::create(2026, 8, 8, 18, 30, 0, 'America/La_Paz');
        $this->travelTo($localTime);
        $product = $this->product();

        $this->actingAs($this->cashier())->post(route('inventory.movements.store', $product), $this->movementData())
            ->assertSessionHasNoErrors();

        $movement = InventoryMovement::query()->firstOrFail();
        $storedTimestamp = DB::table('inventory_movements')->where('id', $movement->id)->value('created_at');
        $this->assertSame('America/La_Paz', config('app.timezone'));
        $this->assertSame('2026-08-08 18:30:00', $movement->created_at->format('Y-m-d H:i:s'));
        $this->assertSame('2026-08-08 18:30:00', (string) $storedTimestamp);

        $this->travelBack();
    }

    /** @param array<string, mixed> $overrides */
    private function movementData(array $overrides = []): array
    {
        return [
            'type' => InventoryMovementType::Entry->value,
            'quantity' => '1.000',
            'reason' => 'Entrada de mercadería',
            'observation' => null,
            ...$overrides,
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

    /** @param array<string, mixed> $attributes */
    private function administrator(array $attributes = []): User
    {
        return User::factory()->create([
            'role_id' => $this->administratorRole->id,
            'branch_id' => $this->branch->id,
            ...$attributes,
        ]);
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
