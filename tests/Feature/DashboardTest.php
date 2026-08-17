<?php

namespace Tests\Feature;

use App\Enums\MeasurementUnit;
use App\Enums\RoleSlug;
use App\Enums\SaleStatus;
use App\Models\Branch;
use App\Models\Category;
use App\Models\Product;
use App\Models\Role;
use App\Models\Sale;
use App\Models\User;
use App\Services\DashboardService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    private Branch $otherBranch;

    private Category $category;

    private Category $otherCategory;

    private Role $administratorRole;

    private Role $cashierRole;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::create(2026, 8, 9, 12, 0, 0, 'America/La_Paz'));
        $this->branch = Branch::factory()->create(['code' => 'PRINCIPAL']);
        $this->otherBranch = Branch::factory()->create(['code' => 'OTRA']);
        $this->category = Category::factory()->create(['branch_id' => $this->branch]);
        $this->otherCategory = Category::factory()->create(['branch_id' => $this->otherBranch]);
        $this->administratorRole = Role::factory()->create(['name' => 'Administrador', 'slug' => RoleSlug::Administrator->value]);
        $this->cashierRole = Role::factory()->create(['name' => 'Cajero', 'slug' => RoleSlug::Cashier->value]);
    }

    public function test_administrator_and_cashier_can_access_but_inactive_and_branchless_users_cannot(): void
    {
        foreach ([$this->administrator(), $this->cashier()] as $user) {
            $this->actingAs($user)->get(route('dashboard'))->assertOk()->assertSee('Ventas de hoy');
        }
        $this->actingAs($this->cashier(['is_active' => false]))->get(route('dashboard'))->assertRedirect(route('login'));
        $this->actingAs($this->administrator(['branch_id' => null]))->get(route('dashboard'))->assertForbidden();
    }

    public function test_profile_trigger_displays_first_name_and_neutral_area_label_without_repeating_them_in_dropdown(): void
    {
        $administrator = $this->administrator(['name' => 'Ronny Aldair Huarachi']);
        $cashier = $this->cashier(['name' => 'Juan Carlos PÃ©rez']);

        $this->actingAs($administrator)->get(route('dashboard'))->assertOk()
            ->assertSeeInOrder(['Ronny', 'Administración'])
            ->assertDontSee('Ronny Aldair Huarachi');
        $this->actingAs($cashier)->get(route('dashboard'))->assertOk()
            ->assertSeeInOrder(['Juan', 'Cajas'])
            ->assertDontSee('Juan Carlos PÃ©rez');
    }

    public function test_navigation_is_grouped_by_area_and_preserves_role_visibility(): void
    {
        $administratorResponse = $this->actingAs($this->administrator())->get(route('dashboard'))->assertOk();
        $administratorResponse
            ->assertSeeInOrder(['Inicio', 'Operaciones', 'Productos', 'Reportes', 'Administraci'])
            ->assertSee(asset('images/quique-logo.png'), false)
            ->assertSee(asset('images/quique-favicon.png'), false)
            ->assertSee('rotate-180', false)
            ->assertSee('rounded-full', false)
            ->assertSee('data-theme-toggle', false)
            ->assertSee('window.toggleTheme()', false)
            ->assertSee(route('pos.index'), false)
            ->assertSee(route('sales.index'), false)
            ->assertSee(route('customers.index'), false)
            ->assertSee(route('products.index'), false)
            ->assertSee(route('categories.index'), false)
            ->assertSee(route('inventory.index'), false)
            ->assertSee(route('reports.index'), false)
            ->assertSee(route('admin.users.index'), false)
            ->assertSee(route('admin.audit.index'), false)
            ->assertSee('@click="operationsOpen = ! operationsOpen"', false)
            ->assertSee('@click="productsOpen = ! productsOpen"', false)
            ->assertSee('@click="administrationOpen = ! administrationOpen"', false);

        $cashierResponse = $this->actingAs($this->cashier())->get(route('dashboard'))->assertOk();
        $cashierResponse
            ->assertSeeInOrder(['Inicio', 'Operaciones', 'Productos', 'Reportes'])
            ->assertSee(route('pos.index'), false)
            ->assertSee(route('sales.index'), false)
            ->assertSee(route('customers.index'), false)
            ->assertSee(route('products.index'), false)
            ->assertSee(route('inventory.index'), false)
            ->assertSee(route('reports.index'), false)
            ->assertDontSee(route('categories.index'), false)
            ->assertDontSee(route('admin.users.index'), false)
            ->assertDontSee(route('admin.audit.index'), false)
            ->assertDontSee('@click="administrationOpen = ! administrationOpen"', false);
    }

    public function test_dashboard_calculates_confirmed_daily_sales_and_payment_breakdown(): void
    {
        $user = $this->cashier();
        $product = $this->product(['name' => 'Producto métrico']);
        $this->sale($user, '10.00', [['cash', '10.00']], $product, '1.000');
        $this->sale($user, '20.00', [['qr', '20.00']], $product, '2.000');
        $this->sale($user, '30.00', [['cash', '10.00'], ['qr', '20.00']], $product, '3.000');
        $this->sale($user, '99.00', [['cash', '99.00']], $product, '1.000', now()->subDay());
        $this->sale($user, '77.00', [['cash', '77.00']], $product, '1.000', now(), SaleStatus::Confirmed->value, $this->otherBranch);

        $this->actingAs($user)->get(route('dashboard'))->assertOk()
            ->assertSee('Ventas confirmadas')->assertSee('>3<', false)
            ->assertSee('Bs 60,00')->assertSee('Bs 20,00')->assertSee('Bs 40,00')->assertSee('Bs 30,00')
            ->assertSeeInOrder(['Efectivo recibido', 'Bs 20,00', '2 operaciones'])
            ->assertSeeInOrder(['Pagos QR', 'Bs 40,00', '2 operaciones'])
            ->assertSeeInOrder(['Ventas con pago mixto', 'Bs 30,00', '1 operación'])
            ->assertDontSee('Bs 99,00')->assertDontSee('Bs 77,00');
    }

    public function test_dashboard_shows_cancelled_sales_separately_without_affecting_income(): void
    {
        $user = $this->administrator();
        $product = $this->product();
        $this->sale($user, '25.00', [['cash', '25.00']], $product, '1.000');
        $this->sale($user, '40.00', [['cash', '40.00']], $product, '1.000', now(), SaleStatus::Cancelled->value);
        $this->sale($user, '90.00', [['cash', '90.00']], $product, '1.000', now()->subDay(), SaleStatus::Cancelled->value);

        $this->actingAs($user)->get(route('dashboard'))->assertOk()
            ->assertSeeInOrder(['Ventas confirmadas', '>1<', 'Bs 25,00'], false)
            ->assertSeeInOrder(['Ventas anuladas hoy: 1', 'Monto anulado: Bs 40,00'])
            ->assertDontSee('Monto anulado: Bs 90,00');
    }

    public function test_top_products_use_historical_names_quantities_and_amounts(): void
    {
        $user = $this->administrator();
        $product = $this->product(['name' => 'Nombre original', 'sale_price' => '5.00', 'unit' => MeasurementUnit::Kilogram]);
        $this->sale($user, '12.50', [['cash', '12.50']], $product, '2.500', now(), SaleStatus::Confirmed->value, null, 'Nombre histórico', '5.00');
        $product->update(['name' => 'Nombre actual', 'sale_price' => '99.00']);

        $this->actingAs($user)->get(route('dashboard'))->assertOk()
            ->assertSee('Nombre histórico')->assertSee('2,500 kg')->assertSee('Bs 12,50')
            ->assertDontSee('Nombre actual')->assertDontSee('Bs 247,50');
    }

    public function test_top_products_format_quantities_with_their_historical_commercial_units(): void
    {
        $user = $this->cashier();
        $units = $this->product(['name' => 'Pan', 'unit' => MeasurementUnit::Unit]);
        $kilograms = $this->product(['name' => 'Pollo', 'unit' => MeasurementUnit::Kilogram]);
        $liters = $this->product(['name' => 'Aceite', 'unit' => MeasurementUnit::Liter]);
        $this->sale($user, '30.00', [['cash', '30.00']], $units, '30.000');
        $this->sale($user, '5.00', [['cash', '5.00']], $kilograms, '0.500');
        $this->sale($user, '15.00', [['cash', '15.00']], $liters, '1.500');

        $this->actingAs($user)->get(route('dashboard'))->assertOk()
            ->assertSee('30 unidades')->assertDontSee('30,000')
            ->assertSee('0,500 kg')->assertSee('1,500 L');
    }

    public function test_stock_and_expiration_attention_follow_existing_rules_and_branch_scope(): void
    {
        $zero = $this->product(['name' => 'Agotado propio', 'stock' => '0.000', 'minimum_stock' => '3.000']);
        $this->product(['name' => 'Bajo propio', 'stock' => '2.000', 'minimum_stock' => '3.000']);
        $this->product(['name' => 'Normal propio', 'stock' => '5.000', 'minimum_stock' => '3.000']);
        $this->product(['name' => 'Inactivo agotado', 'stock' => '0.000', 'is_active' => false]);
        $this->product(['name' => 'Vence pronto propio', 'expires_at' => today()->addDays(7)]);
        $this->product(['name' => 'Vence fuera propio', 'expires_at' => today()->addDays(8)]);
        $this->product(['name' => 'Vencido propio', 'expires_at' => today()->subDay()]);
        $this->product(['name' => 'Agotado ajeno', 'stock' => '0.000'], $this->otherBranch);
        $this->product(['name' => 'Vence pronto ajeno', 'expires_at' => today()->addDay()], $this->otherBranch);

        $response = $this->actingAs($this->cashier())->get(route('dashboard'))->assertOk()
            ->assertSee('Agotado propio')->assertSee('Bajo propio')->assertSee('Vence pronto propio')->assertSee('Vencido propio')
            ->assertSee('0 unidades')->assertSee('Stock: 2 unidades')->assertSee('Mínimo: 3 unidades')
            ->assertDontSee('Normal propio')->assertDontSee('Inactivo agotado')->assertDontSee('Vence fuera propio')
            ->assertDontSee('Agotado ajeno')->assertDontSee('Vence pronto ajeno');
        $response->assertSee(route('inventory.index', ['stock' => 'zero']), false)
            ->assertSee(route('inventory.index', ['stock' => 'low']), false)
            ->assertSee(route('inventory.index', ['expiration' => 'expiring']), false)
            ->assertSee(route('inventory.index', ['expiration' => 'expired']), false);
        $this->assertNotNull($zero);
    }

    public function test_today_uses_lapaz_timezone_at_day_boundaries(): void
    {
        $user = $this->cashier();
        $product = $this->product();
        $this->sale($user, '15.00', [['cash', '15.00']], $product, '1.000', Carbon::create(2026, 8, 9, 0, 5, 0, 'America/La_Paz'));
        $this->sale($user, '25.00', [['cash', '25.00']], $product, '1.000', Carbon::create(2026, 8, 8, 23, 59, 0, 'America/La_Paz'));

        $this->actingAs($user)->get(route('dashboard'))->assertSee('Bs 15,00')->assertDontSee('Bs 25,00');
    }

    public function test_administrator_and_cashier_see_inventory_pdf_action(): void
    {
        foreach ([$this->administrator(), $this->cashier()] as $user) {
            $this->actingAs($user)->get(route('dashboard'))->assertOk()
                ->assertSee('PDF de abastecimiento')->assertSee(route('dashboard.inventory-pdf'), false);
        }
    }

    public function test_cashier_can_download_inventory_pdf_but_invalid_accounts_are_rejected(): void
    {
        $this->actingAs($this->cashier())->get(route('dashboard.inventory-pdf'))
            ->assertOk()->assertHeader('content-type', 'application/pdf')->assertDownload();
        $this->actingAs($this->administrator(['branch_id' => null]))->get(route('dashboard.inventory-pdf'))->assertForbidden();
        $this->actingAs($this->administrator(['is_active' => false]))->get(route('dashboard.inventory-pdf'))->assertRedirect(route('login'));
    }

    public function test_administrator_downloads_real_inventory_pdf_with_dashboard_data_and_branch_isolation(): void
    {
        $administrator = $this->administrator();
        $top = $this->product(['name' => 'Nombre actual', 'unit' => MeasurementUnit::Kilogram]);
        $this->sale($administrator, '25.00', [['cash', '25.00']], $top, '2.500', now(), SaleStatus::Confirmed->value, null, 'Producto histórico vendido');
        $top->update(['name' => 'Nombre modificado', 'unit' => MeasurementUnit::Liter]);
        $zero = $this->product(['name' => 'Producto agotado PDF', 'unit' => MeasurementUnit::Unit, 'stock' => '0.000']);
        $low = $this->product(['name' => 'Producto bajo PDF', 'unit' => MeasurementUnit::Kilogram, 'stock' => '0.500', 'minimum_stock' => '1.000']);
        $expiring = $this->product(['name' => 'Producto próximo PDF', 'unit' => MeasurementUnit::Liter, 'stock' => '2.500', 'expires_at' => today()->addDays(7)]);
        $expired = $this->product(['name' => 'Producto vencido PDF', 'unit' => MeasurementUnit::Unit, 'stock' => '4.000', 'expires_at' => today()->subDay()]);
        $foreignZero = $this->product(['name' => 'Producto agotado ajeno PDF', 'stock' => '0.000'], $this->otherBranch);
        $stocksBefore = Product::query()->whereIn('id', [$zero->id, $low->id, $expiring->id, $expired->id])->pluck('stock', 'id')->all();

        $response = $this->actingAs($administrator)->get(route('dashboard.inventory-pdf', ['branch_id' => $this->otherBranch->id]))
            ->assertOk()->assertHeader('content-type', 'application/pdf')->assertDownload();
        $this->assertStringStartsWith('%PDF-', $response->getContent());
        $this->assertMatchesRegularExpression('/\/MediaBox\s*\[\s*0(?:\.0+)?\s+0(?:\.0+)?\s+792(?:\.0+)?\s+612(?:\.0+)?\s*\]/', $response->getContent());

        $data = app(DashboardService::class)->forBranch($this->branch->id, today());
        $data['branchName'] = $this->branch->name;
        $data['generatedAt'] = now();
        $html = view('dashboard-inventory-pdf', $data)->render();
        $this->assertStringContainsString('Reporte de Inventario y Abastecimiento', $html);
        $this->assertStringNotContainsString('Productos más vendidos de hoy', $html);
        $this->assertStringNotContainsString('Producto histórico vendido', $html);
        $this->assertStringNotContainsString('Nombre modificado', $html);
        $this->assertStringContainsString('Productos agotados', $html);
        $this->assertStringContainsString('Producto agotado PDF', $html);
        $this->assertStringContainsString('0 unidades', $html);
        $this->assertStringContainsString('Productos con stock bajo', $html);
        $this->assertStringContainsString('Producto bajo PDF', $html);
        $this->assertStringContainsString('0,500 kg', $html);
        $this->assertStringContainsString('1,000 kg', $html);
        $this->assertStringContainsString('Productos próximos a vencer', $html);
        $this->assertStringContainsString('Producto próximo PDF', $html);
        $this->assertStringContainsString('2,500 L', $html);
        $this->assertStringContainsString(today()->addDays(7)->format('d/m/Y'), $html);
        $this->assertStringContainsString('Productos vencidos', $html);
        $this->assertStringContainsString('Producto vencido PDF', $html);
        $this->assertStringContainsString('4 unidades', $html);
        $this->assertStringContainsString(today()->subDay()->format('d/m/Y'), $html);
        $this->assertStringContainsString('Zona horaria: UTC-04:00', $html);
        $this->assertStringNotContainsString('Producto agotado ajeno PDF', $html);
        $this->assertStringNotContainsString('Ventas confirmadas', $html);
        $this->assertStringNotContainsString('Efectivo recibido', $html);
        $this->assertStringNotContainsString('Pagos QR', $html);
        $this->assertStringNotContainsString('Operaciones mixtas', $html);
        $this->assertSame($stocksBefore, Product::query()->whereIn('id', array_keys($stocksBefore))->pluck('stock', 'id')->all());
        $this->assertNotNull($foreignZero);
    }

    private function sale(User $user, string $total, array $payments, Product $product, string $quantity, mixed $confirmedAt = null, string $status = 'confirmed', ?Branch $branch = null, ?string $historicalName = null, ?string $unitPrice = null): Sale
    {
        $branch ??= $this->branch;
        if ($branch->id !== $product->branch_id) {
            $product = $this->product(['name' => 'Producto ajeno'], $branch);
        }
        if ($branch->id !== $user->branch_id) {
            $user = User::factory()->create(['role_id' => $this->cashierRole, 'branch_id' => $branch]);
        }
        $sale = Sale::query()->create(['branch_id' => $branch->id, 'user_id' => $user->id, 'sale_number' => 'VTA-'.$branch->code.'-'.fake()->unique()->numerify('######'), 'subtotal' => $total, 'total' => $total, 'status' => $status, 'confirmed_at' => $confirmedAt ?? now()]);
        $price = $unitPrice ?? bcdiv($total, $quantity, 2);
        $sale->items()->create(['product_id' => $product->id, 'product_name' => $historicalName ?? $product->name, 'unit' => $product->unit, 'quantity' => $quantity, 'unit_price' => $price, 'subtotal' => $total]);
        foreach ($payments as [$method, $amount]) {
            $sale->payments()->create(['method' => $method, 'amount' => $amount]);
        }

        return $sale;
    }

    private function product(array $attributes = [], ?Branch $branch = null): Product
    {
        $branch ??= $this->branch;

        return Product::factory()->create(['branch_id' => $branch, 'category_id' => $branch->is($this->branch) ? $this->category : $this->otherCategory, 'unit' => MeasurementUnit::Unit, 'stock' => '10.000', 'minimum_stock' => '0.000', ...$attributes]);
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
