<?php

namespace Tests\Feature;

use App\Enums\InventoryMovementType;
use App\Enums\RaffleParticipationStatus;
use App\Enums\RaffleTicketStatus;
use App\Enums\RoleSlug;
use App\Enums\SaleStatus;
use App\Models\Branch;
use App\Models\Category;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use App\Services\AuditService;
use App\Services\DashboardService;
use App\Services\RaffleParticipationService;
use App\Services\ReportService;
use App\Services\SaleCancellationService;
use App\Services\SaleService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class SaleCancellationTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    private Category $category;

    private Role $administratorRole;

    private Role $cashierRole;

    protected function setUp(): void
    {
        parent::setUp();
        $this->branch = Branch::factory()->create(['code' => 'PRINCIPAL', 'raffle_ticket_threshold' => '1000.00']);
        $this->category = Category::factory()->create(['branch_id' => $this->branch]);
        $this->administratorRole = Role::factory()->create(['slug' => RoleSlug::Administrator->value]);
        $this->cashierRole = Role::factory()->create(['slug' => RoleSlug::Cashier->value]);
    }

    public function test_administrator_and_cashier_can_cancel_sale_and_restore_stock_exactly_once(): void
    {
        foreach ([$this->administrator(), $this->cashier()] as $canceller) {
            $seller = $this->cashier();
            $product = $this->product(['stock' => '10.000', 'sale_price' => '5.00']);
            $sale = $this->sale($seller, $product, '2.000');
            $this->assertSame('8.000', $product->fresh()->stock);

            $this->actingAs($canceller)->post(route('sales.cancel', $sale), ['reason' => '  Producto   devuelto  '])
                ->assertRedirect(route('sales.show', $sale));

            $sale->refresh();
            $this->assertSame(SaleStatus::Cancelled, $sale->status);
            $this->assertSame($canceller->id, $sale->cancelled_by);
            $this->assertNotNull($sale->cancelled_at);
            $this->assertSame('Producto devuelto', $sale->cancellation_reason);
            $this->assertSame('10.000', $product->fresh()->stock);
            $this->assertSame(1, $sale->items()->count());
            $this->assertSame(1, $sale->payments()->count());
            $this->assertSame(1, $sale->inventoryMovements()->where('type', InventoryMovementType::Exit->value)->count());
            $this->assertSame(1, $sale->inventoryMovements()->where('type', InventoryMovementType::SaleReversal->value)->count());
            $this->assertDatabaseHas('audit_logs', ['user_id' => $canceller->id, 'action' => 'Venta anulada', 'auditable_id' => $sale->id]);

            $this->actingAs($canceller)->post(route('sales.cancel', $sale), ['reason' => 'Segundo intento'])->assertSessionHasErrors('sale');
            $this->assertSame('10.000', $product->fresh()->stock);
            $this->assertSame(1, $sale->inventoryMovements()->where('type', InventoryMovementType::SaleReversal->value)->count());
        }
    }

    public function test_cancellation_requires_reason_and_is_branch_protected(): void
    {
        $sale = $this->sale($this->cashier(), $this->product(), '1.000');
        $this->actingAs($this->cashier())->post(route('sales.cancel', $sale), ['reason' => '   '])->assertSessionHasErrors('reason');
        $this->assertSame(SaleStatus::Confirmed, $sale->fresh()->status);

        $otherBranch = Branch::factory()->create();
        $foreign = $this->cashier(['branch_id' => $otherBranch]);
        $this->actingAs($foreign)->post(route('sales.cancel', $sale), ['reason' => 'Intento externo'])->assertForbidden();
    }

    public function test_neither_role_can_cancel_a_sale_from_a_previous_local_day(): void
    {
        $this->travelTo(Carbon::create(2026, 8, 16, 10, 0, 0, 'America/La_Paz'));
        foreach ([$this->administrator(), $this->cashier()] as $user) {
            $product = $this->product(['stock' => '10.000']);
            $sale = $this->sale($this->cashier(), $product, '2.000');
            $sale->update(['confirmed_at' => now()->subDay()]);

            $this->actingAs($user)->post(route('sales.cancel', $sale), ['reason' => 'Intento tardío'])
                ->assertSessionHasErrors(['sale' => 'Esta venta ya no puede ser anulada porque corresponde a una fecha anterior.']);
            $this->assertSame(SaleStatus::Confirmed, $sale->fresh()->status);
            $this->assertSame('8.000', $product->fresh()->stock);
            $this->assertSame(0, $sale->inventoryMovements()->where('type', InventoryMovementType::SaleReversal->value)->count());
            $this->actingAs($user)->get(route('sales.show', $sale))->assertOk()
                ->assertDontSee('Confirmar anulación')->assertSee('Esta venta ya no puede ser anulada porque corresponde a una fecha anterior.');
        }
    }

    public function test_inactive_product_receives_reversal_without_being_reactivated(): void
    {
        $user = $this->cashier();
        $product = $this->product(['stock' => '10.000']);
        $sale = $this->sale($user, $product, '3.000');
        $product->update(['is_active' => false]);

        $this->actingAs($user)->post(route('sales.cancel', $sale), ['reason' => 'Devolución completa'])->assertSessionHasNoErrors();

        $this->assertSame('10.000', $product->fresh()->stock);
        $this->assertFalse($product->fresh()->is_active);
    }

    public function test_cancellation_invalidates_raffle_records_without_deleting_them(): void
    {
        $this->branch->update(['raffle_ticket_threshold' => '10.00']);
        $user = $this->cashier();
        $product = $this->product(['stock' => '10.000', 'sale_price' => '25.00']);
        $sale = $this->sale($user, $product, '2.000');
        app(RaffleParticipationService::class)->accept($user, $sale, [
            'full_name' => 'Cliente Sorteo', 'phone' => '70000001', 'ci' => null,
        ]);
        $ticketCount = $sale->raffleParticipation->tickets()->count();

        app(SaleCancellationService::class)->cancel($user, $sale, 'Venta devuelta');

        $this->assertSame(RaffleParticipationStatus::Cancelled, $sale->raffleParticipation->fresh()->status);
        $this->assertSame($ticketCount, $sale->raffleParticipation->tickets()->count());
        $this->assertSame(0, $sale->raffleParticipation->tickets()->where('status', RaffleTicketStatus::Active->value)->count());
        $this->assertSame($ticketCount, $sale->raffleParticipation->tickets()->where('status', RaffleTicketStatus::Cancelled->value)->count());
        $customer = $sale->refresh()->raffleParticipation->customer;
        $this->actingAs($user)->get(route('customers.show', $customer))->assertOk()
            ->assertSeeInOrder(['Tickets mostrados', (string) $ticketCount, 'Tickets válidos', '0', 'Tickets anulados', (string) $ticketCount])
            ->assertSee('Anulado')->assertSee('bg-red-100', false);
        $this->actingAs($user)->get(route('sales.show', $sale))->assertOk()
            ->assertSee('Anulado')
            ->assertSee('line-through', false);
    }

    public function test_cancelled_sale_is_historical_but_excluded_from_dashboard_reports_and_charts(): void
    {
        $user = $this->cashier();
        $product = $this->product(['sale_price' => '20.00']);
        $sale = $this->sale($user, $product, '1.000');
        app(SaleCancellationService::class)->cancel($user, $sale, 'Error de venta');

        $dashboard = app(DashboardService::class)->forBranch($this->branch->id, today());
        $report = app(ReportService::class)->forBranch($this->branch->id, ['period' => 'today'], false);
        $this->assertSame(0, $dashboard['salesCount']);
        $this->assertSame('0.00', $dashboard['salesTotal']);
        $this->assertCount(0, $dashboard['topProducts']);
        $this->assertSame(0, $report['salesCount']);
        $this->assertCount(0, $report['sales']);
        $this->assertSame(0, array_sum($report['chartData']['sales']));
        $report['branchName'] = $this->branch->name;
        $report['generatedAt'] = now();
        $reportHtml = view('reports.pdf', $report)->render();
        $this->assertStringNotContainsString($sale->sale_number, $reportHtml);
        $this->actingAs($this->administrator())->get(route('reports.pdf', ['period' => 'today']))
            ->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->actingAs($user)->get(route('sales.index'))->assertOk()->assertSee($sale->sale_number)->assertSee('Anulada');
        $this->actingAs($user)->get(route('sales.show', $sale))->assertOk()->assertSee('Venta anulada')->assertSee('Error de venta');
    }

    public function test_failure_at_end_of_cancellation_rolls_back_sale_stock_movements_and_audit(): void
    {
        $user = $this->cashier();
        $product = $this->product(['stock' => '10.000']);
        $sale = $this->sale($user, $product, '2.000');
        $this->assertSame('8.000', $product->fresh()->stock);
        $audit = Mockery::mock(AuditService::class);
        $audit->shouldReceive('record')->once()->andThrow(new \RuntimeException('fallo simulado'));
        $this->app->instance(AuditService::class, $audit);

        try {
            app(SaleCancellationService::class)->cancel($user, $sale, 'Debe revertirse');
            $this->fail('La excepción esperada no fue lanzada.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('fallo simulado', $exception->getMessage());
        }

        $this->assertSame(SaleStatus::Confirmed, $sale->fresh()->status);
        $this->assertSame('8.000', $product->fresh()->stock);
        $this->assertSame(0, $sale->inventoryMovements()->where('type', InventoryMovementType::SaleReversal->value)->count());
        $this->assertDatabaseCount('audit_logs', 0);
    }

    private function sale(User $user, Product $product, string $quantity)
    {
        return app(SaleService::class)->confirm($user, [[
            'product_id' => $product->id, 'quantity' => $quantity, 'expected_price' => $product->sale_price,
        ]], 'cash', '1000.00', null, null);
    }

    private function product(array $attributes = []): Product
    {
        return Product::factory()->create(['branch_id' => $this->branch, 'category_id' => $this->category, 'stock' => '20.000', ...$attributes]);
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
