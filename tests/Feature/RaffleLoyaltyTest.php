<?php

namespace Tests\Feature;

use App\Enums\RaffleParticipationStatus;
use App\Enums\RaffleTicketStatus;
use App\Enums\RoleSlug;
use App\Models\Branch;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Role;
use App\Models\Sale;
use App\Models\User;
use App\Services\RaffleParticipationService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RaffleLoyaltyTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    private Role $administratorRole;

    private Role $cashierRole;

    protected function setUp(): void
    {
        parent::setUp();
        $this->branch = Branch::factory()->create(['raffle_ticket_threshold' => '50.00']);
        $this->administratorRole = Role::factory()->create(['slug' => RoleSlug::Administrator->value]);
        $this->cashierRole = Role::factory()->create(['slug' => RoleSlug::Cashier->value]);
    }

    public static function ticketAmounts(): array
    {
        return [['49.00', 0], ['50.00', 1], ['75.00', 1], ['99.00', 1], ['100.00', 2], ['101.00', 2], ['149.00', 2], ['150.00', 3]];
    }

    #[DataProvider('ticketAmounts')]
    public function test_ticket_count_uses_complete_thresholds_from_one_sale(string $total, int $expected): void
    {
        $sale = $this->sale($total);
        $participation = app(RaffleParticipationService::class)->offer($sale, '50.00');
        $this->assertSame($expected, $participation?->eligible_ticket_count ?? 0);
    }

    public function test_separate_sales_do_not_accumulate_and_historical_sales_are_not_backfilled(): void
    {
        $first = $this->sale('30.00');
        $second = $this->sale('30.00');
        $service = app(RaffleParticipationService::class);
        $this->assertNull($service->offer($first, '50.00'));
        $this->assertNull($service->offer($second, '50.00'));
        $this->assertDatabaseCount('raffle_participations', 0);
    }

    public function test_cashier_can_decline_without_creating_customer_or_ticket(): void
    {
        $cashier = $this->cashier();
        $sale = $this->offeredSale('60.00', $cashier);
        $this->actingAs($cashier)->post(route('sales.raffle.decline', $sale))->assertRedirect();
        $this->assertSame(RaffleParticipationStatus::Declined, $sale->raffleParticipation->fresh()->status);
        $this->assertNull($sale->fresh()->customer_id);
        $this->assertDatabaseCount('customers', 0);
        $this->assertDatabaseCount('raffle_tickets', 0);
    }

    public function test_new_customer_accepts_with_optional_ci_and_receives_correct_sale_and_period_tickets(): void
    {
        $cashier = $this->cashier();
        $sale = $this->offeredSale('100.00', $cashier, '2026-08-12 12:00:00');
        $this->actingAs($cashier)->post(route('sales.raffle.accept', $sale), ['full_name' => '  Ana   Pérez ', 'phone' => '700-12 345'])->assertRedirect();
        $customer = Customer::first();
        $this->assertSame('Ana Pérez', $customer->full_name);
        $this->assertSame('70012345', $customer->phone);
        $this->assertNull($customer->ci);
        $this->assertSame($customer->id, $sale->fresh()->customer_id);
        $this->assertDatabaseCount('raffle_tickets', 2);
        $this->assertTrue($customer->tickets->every(fn ($ticket) => $ticket->sale_id === $sale->id && $ticket->period->starts_on->toDateString() === '2026-07-01'));
    }

    public function test_existing_customer_receives_more_tickets_without_duplicate(): void
    {
        $cashier = $this->cashier();
        $customer = Customer::create(['branch_id' => $this->branch->id, 'full_name' => 'Juan Pérez', 'phone' => '70000001', 'ci' => '123ABC']);
        foreach (['60.00', '130.00'] as $total) {
            $sale = $this->offeredSale($total, $cashier);
            $this->actingAs($cashier)->post(route('sales.raffle.accept', $sale), ['customer_id' => $customer->id])->assertRedirect();
        }
        $this->assertDatabaseCount('customers', 1);
        $this->assertCount(3, $customer->tickets()->get());
    }

    public function test_customer_search_supports_name_phone_and_ci_and_is_branch_isolated(): void
    {
        $cashier = $this->cashier();
        Customer::create(['branch_id' => $this->branch->id, 'full_name' => 'María Flores', 'phone' => '76543210', 'ci' => 'CI-9988']);
        Customer::create(['branch_id' => Branch::factory()->create()->id, 'full_name' => 'María Externa', 'phone' => '71111111']);
        foreach (['María', '7654', 'CI-9988'] as $term) {
            $this->actingAs($cashier)->getJson(route('customers.search', ['search' => $term]))->assertOk()->assertJsonCount(1)->assertJsonPath('0.full_name', 'María Flores');
        }
    }

    public function test_customer_name_and_phone_are_required_and_duplicates_are_rejected(): void
    {
        $cashier = $this->cashier();
        Customer::create(['branch_id' => $this->branch->id, 'full_name' => 'Existente', 'phone' => '70000000', 'ci' => 'ABC1']);
        $sale = $this->offeredSale('50.00', $cashier);
        $this->actingAs($cashier)->post(route('sales.raffle.accept', $sale), [])->assertSessionHasErrors(['full_name', 'phone']);
        $this->post(route('sales.raffle.accept', $sale), ['full_name' => 'Otro', 'phone' => '70000000'])->assertSessionHasErrors('phone');
    }

    public function test_periods_are_bimonthly_and_old_tickets_remain_but_expire(): void
    {
        $cashier = $this->cashier();
        $oldSale = $this->offeredSale('50.00', $cashier, '2026-01-15 10:00:00');
        $this->travelTo(Carbon::parse('2026-01-15 10:01:00'));
        $this->actingAs($cashier)->post(route('sales.raffle.accept', $oldSale), ['full_name' => 'Cliente Uno', 'phone' => '70000009']);
        $customer = Customer::first();
        $this->travelTo(Carbon::parse('2026-03-02 10:00:00'));
        $newSale = $this->offeredSale('50.00', $cashier, '2026-03-02 10:00:00');
        $this->post(route('sales.raffle.accept', $newSale), ['customer_id' => $customer->id]);
        $this->assertDatabaseCount('raffle_tickets', 2);
        $this->assertDatabaseCount('raffle_periods', 2);
        $this->assertSame(RaffleTicketStatus::Expired, $customer->tickets()->orderBy('id')->first()->status);
        $this->assertSame(RaffleTicketStatus::Active, $customer->tickets()->orderByDesc('id')->first()->status);
    }

    public function test_annual_ticket_totals_are_separated_by_calendar_year(): void
    {
        $cashier = $this->cashier();
        $customer = Customer::create([
            'branch_id' => $this->branch->id,
            'full_name' => 'Cliente anual',
            'phone' => '70000999',
        ]);
        $service = app(RaffleParticipationService::class);

        foreach ([
            ['2026-01-15 10:00:00', '100.00'],
            ['2026-03-15 10:00:00', '150.00'],
            ['2027-01-15 10:00:00', '200.00'],
        ] as [$date, $total]) {
            $this->travelTo(Carbon::parse($date));
            $sale = $this->offeredSale($total, $cashier, $date);
            $service->accept($cashier, $sale, ['customer_id' => $customer->id]);
        }

        $annualTotal = fn (int $year): int => $customer->tickets()
            ->whereHas('period', fn ($query) => $query
                ->whereDate('starts_on', '>=', "{$year}-01-01")
                ->whereDate('starts_on', '<=', "{$year}-12-31"))
            ->count();

        $this->assertSame(5, $annualTotal(2026));
        $this->assertSame(4, $annualTotal(2027));
        $this->assertCount(9, $customer->tickets()->get());
        $this->assertSame(5, $customer->tickets()->where('status', RaffleTicketStatus::Expired)->count());
        $this->assertSame(4, $customer->tickets()->where('status', RaffleTicketStatus::Active)->count());

        $this->travelBack();
    }

    public function test_only_administrator_can_change_threshold_and_change_does_not_rewrite_existing_offer(): void
    {
        $cashier = $this->cashier();
        $administrator = User::factory()->create(['branch_id' => $this->branch->id, 'role_id' => $this->administratorRole->id]);
        $sale = $this->offeredSale('100.00', $cashier);
        $this->actingAs($cashier)->patch(route('admin.raffle-settings.update'), ['raffle_ticket_threshold' => '75'])->assertForbidden();
        $this->actingAs($administrator)->patch(route('admin.raffle-settings.update'), ['raffle_ticket_threshold' => '75'])->assertRedirect();
        $this->assertSame('75.00', $this->branch->fresh()->raffle_ticket_threshold);
        $this->assertSame('50.00', $sale->raffleParticipation->threshold_amount);
        $this->assertSame(2, $sale->raffleParticipation->eligible_ticket_count);
    }

    public function test_cross_branch_customer_cannot_be_assigned(): void
    {
        $cashier = $this->cashier();
        $sale = $this->offeredSale('50.00', $cashier);
        $foreign = Customer::create(['branch_id' => Branch::factory()->create()->id, 'full_name' => 'Externo', 'phone' => '78888888']);
        $this->actingAs($cashier)->post(route('sales.raffle.accept', $sale), ['customer_id' => $foreign->id])->assertSessionHasErrors('customer_id');
        $this->assertDatabaseCount('raffle_tickets', 0);
    }

    public function test_pos_cart_quote_is_calculated_by_backend_and_block_starts_hidden_below_threshold(): void
    {
        $cashier = $this->cashier();
        $this->actingAs($cashier);
        $this->putCart(49);
        $this->get(route('pos.index'))->assertOk()->assertSee('raffleTickets: 0', false);
        $this->getJson(route('pos.raffle.quote'))->assertExactJson(['ticket_count' => 0]);

        $this->putCart(100);
        $this->get(route('pos.index'))->assertOk()->assertSee('raffleTickets: 2', false)->assertSee('Esta venta genera');
        $this->getJson(route('pos.raffle.quote'))->assertExactJson(['ticket_count' => 2]);
    }

    public function test_pos_decline_confirms_sale_without_customer_or_tickets(): void
    {
        $cashier = $this->cashier();
        $this->actingAs($cashier);
        $product = $this->putCart(50);
        $this->post(route('pos.sales.store'), $this->posSaleData($product, ['raffle_decision' => 'decline']))->assertRedirect();
        $this->assertDatabaseCount('sales', 1);
        $this->assertDatabaseCount('customers', 0);
        $this->assertDatabaseCount('raffle_tickets', 0);
        $this->assertSame(RaffleParticipationStatus::Declined, Sale::first()->raffleParticipation->status);
    }

    public function test_pos_can_assign_existing_or_new_customer_during_checkout(): void
    {
        $cashier = $this->cashier();
        $existing = Customer::create(['branch_id' => $this->branch->id, 'full_name' => 'Cliente Existente', 'phone' => '70000111']);
        $this->actingAs($cashier);
        $product = $this->putCart(100);
        $this->post(route('pos.sales.store'), $this->posSaleData($product, ['raffle_decision' => 'participate', 'customer_id' => $existing->id]))->assertRedirect();
        $this->assertCount(2, $existing->tickets()->get());

        $product = $this->putCart(50);
        $this->post(route('pos.sales.store'), $this->posSaleData($product, ['raffle_decision' => 'participate', 'customer_full_name' => 'Cliente Nuevo', 'customer_phone' => '70000222']))->assertRedirect();
        $this->assertDatabaseCount('customers', 2);
        $this->assertDatabaseCount('raffle_tickets', 3);
    }

    public function test_pos_customer_search_accepts_partial_name_phone_and_ci(): void
    {
        $cashier = $this->cashier();
        Customer::create(['branch_id' => $this->branch->id, 'full_name' => 'Juan Pérez', 'phone' => '76543210', 'ci' => 'ABC123XYZ']);
        foreach (['J', 'Juan', '7', '543', '123', 'ABC123'] as $term) {
            $this->actingAs($cashier)->getJson(route('customers.search', ['search' => $term]))->assertOk()->assertJsonPath('0.full_name', 'Juan Pérez');
        }
    }

    public function test_customer_module_contains_admin_threshold_modal_but_cashier_does_not_see_action(): void
    {
        $administrator = User::factory()->create(['branch_id' => $this->branch->id, 'role_id' => $this->administratorRole->id]);
        $this->actingAs($administrator)->get(route('customers.index'))->assertOk()->assertSee('Configurar umbral de tickets')->assertSee('Guardar configuración');
        $this->actingAs($this->cashier())->get(route('customers.index'))->assertOk()->assertDontSee('Configurar umbral de tickets')->assertDontSee('Guardar configuración');
        $this->assertFalse(Route::has('admin.raffle-settings.edit'));
    }

    public function test_threshold_input_uses_integer_steps_and_backend_rejects_decimals(): void
    {
        $administrator = User::factory()->create(['branch_id' => $this->branch->id, 'role_id' => $this->administratorRole->id]);
        $this->actingAs($administrator)->get(route('customers.index'))->assertOk()->assertSee('step="1"', false)->assertSee('min="1"', false);
        $this->patch(route('admin.raffle-settings.update'), ['raffle_ticket_threshold' => '50.1'])->assertSessionHasErrors('raffle_ticket_threshold');
        $this->assertSame('50.00', $this->branch->fresh()->raffle_ticket_threshold);
    }

    public function test_eligible_pos_sale_cannot_be_confirmed_while_participation_is_pending(): void
    {
        $cashier = $this->cashier();
        $this->actingAs($cashier);
        $product = $this->putCart(85);
        $this->post(route('pos.sales.store'), $this->posSaleData($product, []))->assertSessionHasErrors('raffle_decision');
        $this->assertDatabaseCount('sales', 0);
        $this->assertSame('10.000', $product->fresh()->stock);
        $this->get(route('pos.index'))->assertSee("this.raffleTickets === 0 || ['participate', 'decline'].includes(this.raffleDecision)", false);
    }

    public function test_customers_are_ordered_by_ticket_count_descending_then_name(): void
    {
        $cashier = $this->cashier();
        $customers = collect([
            Customer::create(['branch_id' => $this->branch->id, 'full_name' => 'Ana Uno', 'phone' => '70000001']),
            Customer::create(['branch_id' => $this->branch->id, 'full_name' => 'Carlos Tres', 'phone' => '70000002']),
            Customer::create(['branch_id' => $this->branch->id, 'full_name' => 'Beatriz Tres', 'phone' => '70000003']),
        ]);
        foreach ([1, 3, 3] as $index => $count) {
            $sale = $this->offeredSale((string) ($count * 50), $cashier);
            $this->actingAs($cashier)->post(route('sales.raffle.accept', $sale), ['customer_id' => $customers[$index]->id]);
        }

        $response = $this->actingAs($cashier)->get(route('customers.index'))->assertOk();
        $response->assertSeeInOrder(['Beatriz Tres', 'Carlos Tres', 'Ana Uno']);
    }

    public function test_new_sale_action_is_visible_in_sale_header(): void
    {
        $cashier = $this->cashier();
        $sale = $this->sale('20.00', $cashier);
        $this->actingAs($cashier)->get(route('sales.show', $sale))->assertOk()->assertSee('Nueva venta')->assertSee(route('pos.index'), false);
    }

    private function putCart(int $total): Product
    {
        $product = Product::factory()->create(['branch_id' => $this->branch->id, 'category_id' => Category::factory()->create(['branch_id' => $this->branch->id])->id, 'sale_price' => (string) $total, 'stock' => '10.000']);
        session(['pos.cart.'.auth()->id() => [$product->id => ['quantity' => '1', 'observed_price' => (string) $product->sale_price]]]);

        return $product;
    }

    private function posSaleData(Product $product, array $extra): array
    {
        return ['items' => [['product_id' => $product->id, 'quantity' => '1']], 'payment_type' => 'cash', 'cash_received' => $product->sale_price, ...$extra];
    }

    private function cashier(): User
    {
        return User::factory()->create(['branch_id' => $this->branch->id, 'role_id' => $this->cashierRole->id]);
    }

    private function sale(string $total, ?User $user = null, ?string $date = null): Sale
    {
        $user ??= $this->cashier();

        return Sale::factory()->create(['branch_id' => $this->branch->id, 'user_id' => $user->id, 'subtotal' => $total, 'total' => $total, 'confirmed_at' => $date ?? now()]);
    }

    private function offeredSale(string $total, User $user, ?string $date = null): Sale
    {
        $sale = $this->sale($total, $user, $date);
        app(RaffleParticipationService::class)->offer($sale, '50.00');

        return $sale->load('raffleParticipation');
    }
}
