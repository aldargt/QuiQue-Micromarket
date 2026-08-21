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
use App\Services\ReportService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ReportTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    private Branch $otherBranch;

    private Category $category;

    private Category $otherCategory;

    private Role $administratorRole;

    private Role $cashierRole;

    private int $sequence = 1;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::create(2026, 8, 10, 12, 0, 0, 'America/La_Paz'));
        $this->branch = Branch::factory()->create(['code' => 'PRINCIPAL']);
        $this->otherBranch = Branch::factory()->create(['code' => 'OTRA']);
        $this->category = Category::factory()->create(['branch_id' => $this->branch]);
        $this->otherCategory = Category::factory()->create(['branch_id' => $this->otherBranch]);
        $this->administratorRole = Role::factory()->create(['slug' => RoleSlug::Administrator->value]);
        $this->cashierRole = Role::factory()->create(['slug' => RoleSlug::Cashier->value]);
    }

    public function test_access_follows_sale_policy_for_both_roles_and_rejects_invalid_accounts(): void
    {
        foreach ([$this->administrator(), $this->cashier()] as $user) {
            $this->actingAs($user)->get(route('reports.index'))->assertOk()->assertSee('Reportes');
        }

        $this->app['auth']->logout();
        $this->get(route('reports.index'))->assertRedirect(route('login'));
        $this->actingAs($this->cashier(['is_active' => false]))->get(route('reports.index'))->assertRedirect(route('login'));
        $this->actingAs($this->administrator(['branch_id' => null]))->get(route('reports.index'))->assertForbidden();
    }

    public function test_today_yesterday_and_specific_date_use_lapaz_day_boundaries(): void
    {
        $user = $this->cashier();
        $product = $this->product();
        $this->sale($user, $product, '10.00', [['cash', '10.00']], '1.000', Carbon::create(2026, 8, 10, 0, 0, 0, 'America/La_Paz'));
        $this->sale($user, $product, '20.00', [['qr', '20.00']], '1.000', Carbon::create(2026, 8, 10, 23, 59, 59, 'America/La_Paz'));
        $this->sale($user, $product, '30.00', [['cash', '30.00']], '1.000', Carbon::create(2026, 8, 9, 23, 59, 59, 'America/La_Paz'));

        $this->actingAs($user)->get(route('reports.index', ['period' => 'today']))->assertOk()
            ->assertSee('Bs 30,00')->assertDontSee('Bs 60,00');
        $this->actingAs($user)->get(route('reports.index', ['period' => 'yesterday']))->assertOk()
            ->assertSee('Ayer')->assertSee('Bs 30,00')->assertDontSee('Bs 20,00');
        $this->actingAs($user)->get(route('reports.index', ['period' => 'date', 'date' => '2026-08-09']))->assertOk()
            ->assertSee('09 de agosto de 2026')->assertSee('Bs 30,00')->assertDontSee('Bs 20,00');
    }

    public function test_inclusive_single_and_multi_day_ranges_and_invalid_order(): void
    {
        $user = $this->administrator();
        $product = $this->product();
        $this->sale($user, $product, '10.00', [['cash', '10.00']], '1.000', Carbon::create(2026, 8, 1, 0, 0, 0, 'America/La_Paz'));
        $this->sale($user, $product, '20.00', [['cash', '20.00']], '1.000', Carbon::create(2026, 8, 2, 12, 0, 0, 'America/La_Paz'));
        $this->sale($user, $product, '30.00', [['cash', '30.00']], '1.000', Carbon::create(2026, 8, 3, 23, 59, 59, 'America/La_Paz'));

        $this->actingAs($user)->get(route('reports.index', ['period' => 'range', 'start' => '2026-08-02', 'end' => '2026-08-02']))
            ->assertOk()->assertSee('Bs 20,00')->assertDontSee('Bs 60,00');
        $this->actingAs($user)->get(route('reports.index', ['period' => 'range', 'start' => '2026-08-01', 'end' => '2026-08-03']))
            ->assertOk()->assertSee('Bs 60,00')->assertSee('01/08/2026')->assertSee('03/08/2026');
        $this->actingAs($user)->get(route('reports.index', ['period' => 'range', 'start' => '2026-08-03', 'end' => '2026-08-01']))
            ->assertSessionHasErrors('end');
    }

    public function test_summary_counts_cash_qr_and_mixed_operations_without_double_counting_sales(): void
    {
        $user = $this->cashier();
        $product = $this->product();
        $this->sale($user, $product, '10.00', [['cash', '10.00']]);
        $this->sale($user, $product, '20.00', [['qr', '20.00']]);
        $this->sale($user, $product, '30.00', [['cash', '12.00'], ['qr', '18.00']]);

        $this->actingAs($user)->get(route('reports.index'))->assertOk()
            ->assertSeeInOrder(['Ventas confirmadas', '>3<', 'Bs 60,00'], false)
            ->assertSeeInOrder(['Efectivo recibido', 'Bs 22,00', '2 operaciones'])
            ->assertSeeInOrder(['Pagos QR', 'Bs 38,00', '2 operaciones'])
            ->assertSeeInOrder(['Pagos mixtos', '>1<', 'Bs 30,00'], false);
    }

    public function test_cancelled_metrics_work_for_day_range_and_month_without_affecting_income(): void
    {
        $user = $this->administrator();
        $product = $this->product();
        $confirmed = $this->sale($user, $product, '25.00', [['cash', '25.00']], confirmedAt: Carbon::create(2026, 8, 10, 9, 0, 0, 'America/La_Paz'));
        $cancelled = $this->sale($user, $product, '40.00', [['cash', '40.00']], confirmedAt: Carbon::create(2026, 8, 10, 10, 0, 0, 'America/La_Paz'));
        $cancelled->update(['status' => SaleStatus::Cancelled]);

        foreach ([
            ['period' => 'date', 'date' => '2026-08-10'],
            ['period' => 'range', 'start' => '2026-08-09', 'end' => '2026-08-11'],
            ['period' => 'month', 'month' => '2026-08'],
        ] as $filters) {
            $data = app(ReportService::class)->forBranch($this->branch->id, $filters, false);
            $this->assertSame(1, $data['salesCount']);
            $this->assertSame(25.0, (float) $data['salesTotal']);
            $this->assertSame(1, $data['cancelledSalesCount']);
            $this->assertSame(40.0, (float) $data['cancelledSalesTotal']);
            $this->assertSame(1, array_sum($data['chartData']['sales']));
        }

        $this->actingAs($user)->get(route('reports.index', ['period' => 'date', 'date' => '2026-08-10']))->assertOk()
            ->assertSee('Ventas anuladas: 1')->assertSee('Monto anulado: Bs 40,00')->assertSee('Bs 25,00 vendidos');
        $this->actingAs($user)->get(route('reports.pdf', ['period' => 'date', 'date' => '2026-08-10']))
            ->assertOk()->assertHeader('content-type', 'application/pdf');
        $pdfData = app(ReportService::class)->forBranch($this->branch->id, ['period' => 'date', 'date' => '2026-08-10'], false);
        $pdfData['branchName'] = $this->branch->name;
        $pdfData['generatedAt'] = now();
        $pdfHtml = view('reports.pdf', $pdfData)->render();
        $this->assertStringContainsString('Ventas anuladas', $pdfHtml);
        $this->assertStringContainsString('Monto anulado', $pdfHtml);
        $this->assertStringContainsString('Bs 40,00', $pdfHtml);
        $this->assertStringContainsString('<td colspan="2"><div class="label">Ventas anuladas</div>', $pdfHtml);
        $this->assertStringContainsString('<td colspan="2"><div class="label">Monto anulado</div>', $pdfHtml);
        $this->assertStringContainsString('class="summary summary-cancellations"', $pdfHtml);
        $this->assertStringContainsString('.summary-cancellations td { width: 50%; }', $pdfHtml);
        $this->assertStringContainsString('<p class="commercial-criteria"><strong>Criterio comercial:</strong>', $pdfHtml);
        $this->assertStringNotContainsString('<td colspan="2"><div class="label">Criterio comercial</div>', $pdfHtml);
        $this->assertNotNull($confirmed);
    }

    public function test_only_confirmed_sales_from_authenticated_users_branch_are_reported(): void
    {
        $user = $this->cashier();
        $product = $this->product();
        $this->sale($user, $product, '15.00', [['cash', '15.00']]);
        $foreignUser = $this->cashier(['branch_id' => $this->otherBranch->id]);
        $this->sale($foreignUser, $this->product([], $this->otherBranch), '99.00', [['cash', '99.00']]);
        DB::table('sales')->insert([
            'branch_id' => $this->branch->id, 'user_id' => $user->id, 'sale_number' => 'PENDIENTE-001',
            'subtotal' => '77.00', 'total' => '77.00', 'status' => 'pending', 'confirmed_at' => now(),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->actingAs($user)->get(route('reports.index', ['branch_id' => $this->otherBranch->id]))->assertOk()
            ->assertSee('Bs 15,00')->assertDontSee('Bs 99,00')->assertDontSee('Bs 77,00')->assertDontSee('PENDIENTE-001');
    }

    public function test_month_report_has_daily_summary_and_historical_product_data(): void
    {
        $user = $this->administrator();
        $product = $this->product(['name' => 'Nombre actual', 'unit' => MeasurementUnit::Unit]);
        $this->sale($user, $product, '24.00', [['cash', '24.00']], '30.000', Carbon::create(2026, 8, 2, 10, 0, 0, 'America/La_Paz'), 'Pan histórico', MeasurementUnit::Unit);
        $this->sale($user, $product, '25.00', [['qr', '25.00']], '12.500', Carbon::create(2026, 8, 3, 11, 0, 0, 'America/La_Paz'), 'Pollo histórico', MeasurementUnit::Kilogram);
        $product->update(['name' => 'Nombre modificado', 'unit' => MeasurementUnit::Liter]);

        $this->actingAs($user)->get(route('reports.index', ['period' => 'month', 'month' => '2026-08']))->assertOk()
            ->assertSee('Agosto de 2026')->assertSee('Resumen por día')->assertSee('02/08/2026')->assertSee('03/08/2026')
            ->assertSee('Pan histórico')->assertSee('30 unidades')->assertSee('Bs 24,00')
            ->assertSee('Pollo histórico')->assertSee('12,500 kg')->assertSee('Bs 25,00')
            ->assertDontSee('Nombre modificado')->assertDontSee('12,500 L');
    }

    public function test_empty_and_invalid_periods_are_handled_clearly(): void
    {
        $user = $this->cashier();
        $this->actingAs($user)->get(route('reports.index', ['period' => 'date', 'date' => '2025-01-01']))->assertOk()
            ->assertSee('No existen ventas confirmadas en el período seleccionado.')
            ->assertSee('No se vendieron productos durante este período.');
        $this->actingAs($user)->get(route('reports.index', ['period' => 'date', 'date' => 'fecha-inválida']))
            ->assertSessionHasErrors('date');
    }

    public function test_report_screen_separates_the_three_modes_and_only_administrator_sees_pdf_action(): void
    {
        $administrator = $this->administrator();
        $this->actingAs($administrator)->get(route('reports.index'))->assertOk()
            ->assertSeeInOrder(['Reporte por día', 'Reporte por rango', 'Reporte mensual'])
            ->assertSee('Hoy')->assertSee('Ayer')->assertSee('Fecha específica')
            ->assertSee('report-period-controls', false)
            ->assertSee('Descargar PDF')->assertSee('línea continua con círculos')->assertSee('línea punteada con cuadrados')
            ->assertSee('stroke-dasharray="1 7"', false)->assertSee('stroke-linecap="round"', false)
            ->assertSee('margin:0 auto', false)->assertSee('<polyline', false)->assertDontSee('<canvas', false);

        $this->actingAs($this->cashier())->get(route('reports.index'))->assertOk()
            ->assertSee('Reporte por día')->assertSee('Reporte por rango')->assertSee('Reporte mensual')
            ->assertDontSee('Descargar PDF');
    }

    public function test_daily_chart_contains_all_local_hours_and_only_confirmed_branch_sales(): void
    {
        $user = $this->cashier();
        $product = $this->product();
        $this->sale($user, $product, '10.00', [['cash', '10.00']], '1.000', Carbon::create(2026, 8, 10, 8, 5, 0, 'America/La_Paz'));
        $this->sale($user, $product, '25.00', [['cash', '25.00']], '1.000', Carbon::create(2026, 8, 10, 8, 59, 59, 'America/La_Paz'));
        $foreignUser = $this->cashier(['branch_id' => $this->otherBranch->id]);
        $this->sale($foreignUser, $this->product([], $this->otherBranch), '99.00', [['cash', '99.00']], '1.000', Carbon::create(2026, 8, 10, 8, 30, 0, 'America/La_Paz'));
        DB::table('sales')->insert([
            'branch_id' => $this->branch->id, 'user_id' => $user->id, 'sale_number' => 'PENDIENTE-GRAFICA',
            'subtotal' => '77.00', 'total' => '77.00', 'status' => 'pending',
            'confirmed_at' => Carbon::create(2026, 8, 10, 8, 30, 0, 'America/La_Paz'), 'created_at' => now(), 'updated_at' => now(),
        ]);

        $chart = app(ReportService::class)->forBranch($this->branch->id, ['period' => 'date', 'date' => '2026-08-10'])['chartData'];
        $this->assertSame('Ventas por hora', $chart['title']);
        $this->assertCount(24, $chart['labels']);
        $this->assertSame('00:00', $chart['labels'][0]);
        $this->assertSame('23:00', $chart['labels'][23]);
        $this->assertSame(2, $chart['sales'][8]);
        $this->assertSame(35.0, $chart['totals'][8]);
        $this->assertSame(0, $chart['sales'][12]);
        $this->assertSame(0.0, $chart['totals'][12]);
    }

    public function test_range_chart_includes_zero_days_in_chronological_order(): void
    {
        $user = $this->administrator();
        $product = $this->product();
        $this->sale($user, $product, '10.00', [['cash', '10.00']], '1.000', Carbon::create(2026, 8, 1, 9, 0, 0, 'America/La_Paz'));
        $this->sale($user, $product, '30.00', [['qr', '30.00']], '1.000', Carbon::create(2026, 8, 3, 9, 0, 0, 'America/La_Paz'));

        $chart = app(ReportService::class)->forBranch($this->branch->id, ['period' => 'range', 'start' => '2026-08-01', 'end' => '2026-08-03'])['chartData'];
        $this->assertSame(['01/08', '02/08', '03/08'], $chart['labels']);
        $this->assertSame([1, 0, 1], $chart['sales']);
        $this->assertSame([10.0, 0.0, 30.0], $chart['totals']);
    }

    public function test_month_chart_contains_every_calendar_day_including_zero_days(): void
    {
        $user = $this->cashier();
        $product = $this->product();
        $this->sale($user, $product, '18.00', [['cash', '18.00']], '1.000', Carbon::create(2026, 2, 14, 12, 0, 0, 'America/La_Paz'));

        $chart = app(ReportService::class)->forBranch($this->branch->id, ['period' => 'month', 'month' => '2026-02'])['chartData'];
        $this->assertCount(28, $chart['labels']);
        $this->assertSame('01', $chart['labels'][0]);
        $this->assertSame('28', $chart['labels'][27]);
        $this->assertSame(1, $chart['sales'][13]);
        $this->assertSame(18.0, $chart['totals'][13]);
        $this->assertSame(0, $chart['sales'][0]);
    }

    public function test_only_administrator_can_download_real_pdf_for_requested_period_and_branch(): void
    {
        $administrator = $this->administrator();
        $product = $this->product(['name' => 'Nombre actual', 'unit' => MeasurementUnit::Liter]);
        $this->sale($administrator, $product, '25.00', [['cash', '10.00'], ['qr', '15.00']], '12.500', now(), 'Pollo histórico PDF', MeasurementUnit::Kilogram);
        $foreignAdministrator = $this->administrator(['branch_id' => $this->otherBranch->id]);
        $this->sale($foreignAdministrator, $this->product([], $this->otherBranch), '99.00', [['cash', '99.00']]);
        $parameters = ['period' => 'date', 'date' => '2026-08-10', 'branch_id' => $this->otherBranch->id];

        $response = $this->actingAs($administrator)->get(route('reports.pdf', $parameters))->assertOk()
            ->assertHeader('content-type', 'application/pdf')
            ->assertDownload();
        $this->assertStringStartsWith('%PDF-', $response->getContent());
        $this->assertStringContainsString('/Subtype /Image', $response->getContent());
        $this->assertMatchesRegularExpression('/\/MediaBox\s*\[\s*0(?:\.0+)?\s+0(?:\.0+)?\s+792(?:\.0+)?\s+612(?:\.0+)?\s*\]/', $response->getContent());
        $this->assertGreaterThan(40, substr_count($this->decodedPdfStreams($response->getContent()), ' l'));

        $data = app(ReportService::class)->forBranch($this->branch->id, $parameters, false);
        $data['branchName'] = $this->branch->name;
        $data['generatedAt'] = now();
        $html = view('reports.pdf', $data)->render();
        $this->assertStringContainsString('class="pdf-logo"', $html);
        $this->assertStringContainsString('data:image/png;base64,', $html);
        $this->assertStringContainsString('right: 0', $html);
        $this->assertStringContainsString('height: 52px', $html);
        $this->assertStringContainsString('width: 52px', $html);
        $this->assertStringNotContainsString('left: 0', $html);
        $this->assertStringContainsString('10 de agosto de 2026', $html);
        $this->assertStringContainsString('Pollo histórico PDF', $html);
        $this->assertStringContainsString('12,500 kg', $html);
        $this->assertStringContainsString('Bs 25,00', $html);
        $this->assertStringContainsString('Zona horaria: UTC-04:00', $html);
        $this->assertStringNotContainsString('America/La_Paz', $html);
        $this->assertStringNotContainsString('Bs 99,00', $html);
        $this->assertStringNotContainsString('12,500 L', $html);

        $this->actingAs($this->cashier())->get(route('reports.pdf', $parameters))->assertForbidden();
        $this->actingAs($this->administrator(['branch_id' => null]))->get(route('reports.pdf', $parameters))->assertForbidden();
        $this->actingAs($this->administrator(['is_active' => false]))->get(route('reports.pdf', $parameters))->assertRedirect(route('login'));
    }

    public function test_pdf_includes_the_same_two_line_chart_for_day_range_and_month(): void
    {
        $administrator = $this->administrator();
        $product = $this->product();
        $this->sale($administrator, $product, '20.00', [['cash', '20.00']], '1.000', Carbon::create(2026, 8, 10, 8, 0, 0, 'America/La_Paz'));
        $periods = [
            ['period' => 'date', 'date' => '2026-08-10', 'expectedTitle' => 'Ventas por hora', 'expectedLabel' => '00:00', 'expectedWidth' => 912],
            ['period' => 'range', 'start' => '2026-08-09', 'end' => '2026-08-10', 'expectedTitle' => 'Ventas por día', 'expectedLabel' => '09/08', 'expectedWidth' => 760],
            ['period' => 'month', 'month' => '2026-08', 'expectedTitle' => 'Ventas por día', 'expectedLabel' => '>01<', 'expectedWidth' => 960],
        ];

        foreach ($periods as $period) {
            $expectedTitle = $period['expectedTitle'];
            $expectedLabel = $period['expectedLabel'];
            $expectedWidth = $period['expectedWidth'];
            unset($period['expectedTitle'], $period['expectedLabel'], $period['expectedWidth']);
            $data = app(ReportService::class)->forBranch($this->branch->id, $period, false);
            $data['branchName'] = $this->branch->name;
            $data['generatedAt'] = now();
            $html = view('reports.pdf', $data)->render();
            $this->assertStringContainsString('data:image/svg+xml;base64,', $html);
            $this->assertSame(1, preg_match('/data:image\/svg\+xml;base64,([^"\s]+)/', $html, $encodedChart));
            $svg = base64_decode($encodedChart[1], true);
            $this->assertIsString($svg);
            $this->assertSame(2, substr_count($svg, '<polyline'));
            $this->assertStringContainsString('stroke-dasharray="1 7"', $svg);
            $this->assertStringContainsString('stroke-linecap="round"', $svg);
            $this->assertStringContainsString($expectedTitle, $html);
            $this->assertMatchesRegularExpression('/'.preg_quote($expectedLabel, '/').'/', $svg);
            $this->assertStringContainsString('class="report-chart-wrap"', $html);
            $this->assertStringContainsString('width="'.$expectedWidth.'"', $html);
            $this->assertStringContainsString('height: auto', $html);
            $this->assertStringNotContainsString('.report-chart { display: block; height: 250px; width: 100%; }', $html);

            $pdf = $this->actingAs($administrator)->get(route('reports.pdf', $period))->assertOk()
                ->assertHeader('content-type', 'application/pdf');
            $this->assertGreaterThan(40, substr_count($this->decodedPdfStreams($pdf->getContent()), ' l'));
        }
    }

    private function sale(User $user, Product $product, string $total, array $payments, string $quantity = '1.000', mixed $confirmedAt = null, ?string $historicalName = null, ?MeasurementUnit $historicalUnit = null): Sale
    {
        $sale = Sale::query()->create([
            'branch_id' => $user->branch_id, 'user_id' => $user->id,
            'sale_number' => 'VTA-TEST-'.str_pad((string) $this->sequence++, 4, '0', STR_PAD_LEFT),
            'subtotal' => $total, 'total' => $total, 'status' => SaleStatus::Confirmed,
            'confirmed_at' => $confirmedAt ?? now(),
        ]);
        $sale->items()->create([
            'product_id' => $product->id, 'product_name' => $historicalName ?? $product->name,
            'unit' => $historicalUnit ?? $product->unit, 'quantity' => $quantity,
            'unit_price' => bcdiv($total, $quantity, 2), 'subtotal' => $total,
        ]);
        foreach ($payments as [$method, $amount]) {
            $sale->payments()->create(['method' => $method, 'amount' => $amount]);
        }

        return $sale;
    }

    private function decodedPdfStreams(string $pdf): string
    {
        preg_match_all('/stream\r?\n(.*?)\r?\nendstream/s', $pdf, $matches);

        return collect($matches[1])->map(function (string $stream): string {
            $decoded = @gzuncompress($stream);

            return is_string($decoded) ? $decoded : '';
        })->implode("\n");
    }

    private function product(array $attributes = [], ?Branch $branch = null): Product
    {
        $branch ??= $this->branch;

        return Product::factory()->create([
            'branch_id' => $branch,
            'category_id' => $branch->is($this->branch) ? $this->category : $this->otherCategory,
            ...$attributes,
        ]);
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
