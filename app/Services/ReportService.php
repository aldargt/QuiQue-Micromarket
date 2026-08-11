<?php

namespace App\Services;

use App\Enums\SaleStatus;
use App\Models\Sale;
use App\Models\SaleItem;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ReportService
{
    public function __construct(private readonly SalesSummaryService $summaries) {}

    /** @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function forBranch(int $branchId, array $filters, bool $paginateSales = true): array
    {
        $period = $this->resolvePeriod($filters);
        $sales = $this->confirmedSales($branchId, $period['start'], $period['endExclusive']);

        $summary = $this->summaries->forPeriod($branchId, $period['start'], $period['endExclusive']);

        $products = SaleItem::query()
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->where('sales.branch_id', $branchId)
            ->where('sales.status', SaleStatus::Confirmed->value)
            ->where('sales.confirmed_at', '>=', $period['start'])
            ->where('sales.confirmed_at', '<', $period['endExclusive'])
            ->select(['sale_items.product_id', 'sale_items.product_name', 'sale_items.unit'])
            ->selectRaw('SUM(sale_items.quantity) as quantity_sold')
            ->selectRaw('SUM(sale_items.subtotal) as amount_generated')
            ->groupBy('sale_items.product_id', 'sale_items.product_name', 'sale_items.unit')
            ->orderByDesc('amount_generated')->get();

        $products->each(fn ($item) => $item->quantity_display = $item->unit->formatQuantity($item->quantity_sold));
        $dailySummary = (clone $sales)->selectRaw('DATE(confirmed_at) as day, COUNT(*) as sales_count, SUM(total) as total')->groupByRaw('DATE(confirmed_at)')->orderBy('day')->get();

        return [
            'filters' => $period['filters'],
            'periodLabel' => $period['label'],
            ...$summary,
            'products' => $products,
            'dailySummary' => $dailySummary,
            'chartData' => $this->chartData($sales, $period, $dailySummary),
            'sales' => $paginateSales
                ? (clone $sales)->with(['user', 'payments'])->latest('confirmed_at')->paginate(20)->withQueryString()
                : (clone $sales)->with(['user', 'payments'])->latest('confirmed_at')->get(),
        ];
    }

    /** @param array{start: CarbonImmutable, endExclusive: CarbonImmutable, label: string, filters: array<string, string>} $period
     * @return array{title: string, interval: string, labels: array<int, string>, sales: array<int, int>, totals: array<int, float>}
     */
    private function chartData(Builder $sales, array $period, Collection $dailySummary): array
    {
        if (in_array($period['filters']['period'], ['today', 'yesterday', 'date'], true)) {
            $hourExpression = DB::connection()->getDriverName() === 'sqlite'
                ? "CAST(strftime('%H', confirmed_at) AS INTEGER)"
                : 'HOUR(confirmed_at)';
            $hourly = (clone $sales)->selectRaw("{$hourExpression} as interval_key, COUNT(*) as sales_count, SUM(total) as total")
                ->groupByRaw($hourExpression)->get()->keyBy('interval_key');

            return [
                'title' => 'Ventas por hora',
                'interval' => 'hour',
                'labels' => array_map(fn (int $hour): string => str_pad((string) $hour, 2, '0', STR_PAD_LEFT).':00', range(0, 23)),
                'sales' => array_map(fn (int $hour): int => (int) ($hourly->get($hour)?->sales_count ?? 0), range(0, 23)),
                'totals' => array_map(fn (int $hour): float => (float) ($hourly->get($hour)?->total ?? 0), range(0, 23)),
            ];
        }

        $byDay = $dailySummary->keyBy('day');
        $dates = [];
        for ($date = $period['start']; $date->lt($period['endExclusive']); $date = $date->addDay()) {
            $dates[] = $date;
        }
        $monthly = $period['filters']['period'] === 'month';

        return [
            'title' => 'Ventas por día',
            'interval' => 'day',
            'labels' => array_map(fn (CarbonImmutable $date): string => $date->format($monthly ? 'd' : 'd/m'), $dates),
            'sales' => array_map(fn (CarbonImmutable $date): int => (int) ($byDay->get($date->toDateString())?->sales_count ?? 0), $dates),
            'totals' => array_map(fn (CarbonImmutable $date): float => (float) ($byDay->get($date->toDateString())?->total ?? 0), $dates),
        ];
    }

    private function confirmedSales(int $branchId, CarbonImmutable $start, CarbonImmutable $endExclusive): Builder
    {
        return Sale::query()->where('branch_id', $branchId)
            ->where('status', SaleStatus::Confirmed->value)
            ->where('confirmed_at', '>=', $start)
            ->where('confirmed_at', '<', $endExclusive);
    }

    /** @param array<string, mixed> $filters
     * @return array{start: CarbonImmutable, endExclusive: CarbonImmutable, label: string, filters: array<string, string>}
     */
    private function resolvePeriod(array $filters): array
    {
        $timezone = config('app.timezone');
        $today = CarbonImmutable::today($timezone);
        $type = $filters['period'] ?? 'today';

        if ($type === 'yesterday') {
            $start = $today->subDay();
            $endExclusive = $today;
            $label = 'Ayer, '.$start->translatedFormat('d \d\e F \d\e Y');
        } elseif ($type === 'date') {
            $start = CarbonImmutable::createFromFormat('!Y-m-d', $filters['date'], $timezone);
            $endExclusive = $start->addDay();
            $label = $start->translatedFormat('d \d\e F \d\e Y');
        } elseif ($type === 'range') {
            $start = CarbonImmutable::createFromFormat('!Y-m-d', $filters['start'], $timezone);
            $lastDay = CarbonImmutable::createFromFormat('!Y-m-d', $filters['end'], $timezone);
            $endExclusive = $lastDay->addDay();
            $label = 'Del '.$start->format('d/m/Y').' al '.$lastDay->format('d/m/Y');
        } elseif ($type === 'month') {
            $start = CarbonImmutable::createFromFormat('!Y-m', $filters['month'], $timezone)->startOfMonth();
            $endExclusive = $start->addMonth();
            $label = $start->translatedFormat('F \d\e Y');
        } else {
            $type = 'today';
            $start = $today;
            $endExclusive = $today->addDay();
            $label = 'Hoy, '.$today->translatedFormat('d \d\e F \d\e Y');
        }

        return [
            'start' => $start,
            'endExclusive' => $endExclusive,
            'label' => ucfirst($label),
            'filters' => [
                'period' => $type,
                'date' => $filters['date'] ?? $today->toDateString(),
                'start' => $filters['start'] ?? $today->startOfMonth()->toDateString(),
                'end' => $filters['end'] ?? $today->toDateString(),
                'month' => $filters['month'] ?? $today->format('Y-m'),
            ],
        ];
    }
}
