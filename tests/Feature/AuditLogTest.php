<?php

namespace Tests\Feature;

use App\Enums\RoleSlug;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductPriceHistory;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLogTest extends TestCase
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

    public function test_product_creation_and_toggle_are_audited_without_duplicate_price_audit(): void
    {
        $cashier = $this->cashier();
        $this->actingAs($cashier)->post(route('products.store'), $this->validData())->assertSessionHasNoErrors();
        $product = Product::query()->sole();
        $creation = AuditLog::query()->where('action', 'Producto creado')->sole();
        $this->assertSame($cashier->id, $creation->user_id);
        $this->assertSame($this->branch->id, $creation->branch_id);
        $this->assertSame($product->id, $creation->auditable_id);

        $this->actingAs($cashier)->put(route('products.update', $product), [
            ...$this->validData(), 'purchase_price' => '12.00', 'sale_price' => '18.00',
        ])->assertSessionHasNoErrors();
        $this->assertSame(1, ProductPriceHistory::query()->count());
        $this->assertSame(0, AuditLog::query()->where('action', 'Precio de compra modificado')->count());
        $this->assertSame(0, AuditLog::query()->where('action', 'Precio de venta modificado')->count());

        $this->actingAs($cashier)->patch(route('products.toggle', $product))->assertSessionHasNoErrors();
        $toggle = AuditLog::query()->where('action', 'Producto desactivado')->sole();
        $this->assertSame(['is_active' => true], $toggle->old_values);
        $this->assertSame(['is_active' => false], $toggle->new_values);
    }

    public function test_only_administrator_can_view_branch_audit_and_no_sensitive_values_are_recorded(): void
    {
        $product = Product::factory()->create(['branch_id' => $this->branch, 'category_id' => $this->category]);
        AuditLog::query()->create([
            'branch_id' => $this->branch->id, 'user_id' => $this->administrator()->id,
            'action' => 'Producto creado', 'auditable_type' => Product::class, 'auditable_id' => $product->id,
            'old_values' => null, 'new_values' => ['name' => $product->name],
        ]);

        $this->actingAs($this->administrator())->get(route('admin.audit.index'))->assertOk()
            ->assertSee('Auditoría')->assertSee('Producto creado')->assertSee($product->name);
        $serializedValues = json_encode(AuditLog::query()->get(['old_values', 'new_values'])->toArray());
        $this->assertStringNotContainsString('password', $serializedValues);
        $this->assertStringNotContainsString('token', $serializedValues);
        $this->assertStringNotContainsString('secret', $serializedValues);
        $this->actingAs($this->cashier())->get(route('admin.audit.index'))->assertForbidden();
        $this->actingAs($this->cashier())->get(route('dashboard'))->assertOk()->assertDontSee('Auditoría');
    }

    private function validData(): array
    {
        return [
            'name' => 'Café molido', 'barcode' => '', 'category_id' => $this->category->id,
            'unit' => 'unit', 'purchase_price' => '10.00', 'sale_price' => '15.00',
            'minimum_stock' => '2', 'expires_at' => '',
        ];
    }

    private function administrator(): User
    {
        return User::factory()->create(['role_id' => $this->administratorRole, 'branch_id' => $this->branch]);
    }

    private function cashier(): User
    {
        return User::factory()->create(['role_id' => $this->cashierRole, 'branch_id' => $this->branch]);
    }
}
