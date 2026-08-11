<?php

namespace App\Services;

use App\Enums\SaleStatus;
use App\Models\Product;
use App\Models\SaleItem;
use Carbon\CarbonInterface;

class DashboardService
{
    public function __construct(private readonly SalesSummaryService $summaries) {}

    /** @return array<string, mixed> */
    public function forBranch(int $branchId, CarbonInterface $day): array
    {
        $start = $day->copy()->startOfDay();
        $endExclusive = $day->copy()->addDay()->startOfDay();
        $summary = $this->summaries->forPeriod($branchId, $start, $endExclusive);

        $topProducts = SaleItem::query()
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->where('sales.branch_id', $branchId)->where('sales.status', SaleStatus::Confirmed->value)
            ->where('sales.confirmed_at', '>=', $start)->where('sales.confirmed_at', '<', $endExclusive)
            ->select(['sale_items.product_id', 'sale_items.product_name', 'sale_items.unit'])
            ->selectRaw('SUM(sale_items.quantity) as quantity_sold')
            ->selectRaw('SUM(sale_items.subtotal) as amount_generated')
            ->groupBy('sale_items.product_id', 'sale_items.product_name', 'sale_items.unit')
            ->orderByDesc('quantity_sold')->limit(8)->get();
        $topProducts->each(function ($item): void {
            $item->quantity_display = $item->unit->formatQuantity($item->quantity_sold);
        });

        $activeProducts = Product::query()->where('branch_id', $branchId)->where('is_active', true);
        $zeroStock = (clone $activeProducts)->where('stock', 0);
        $lowStock = (clone $activeProducts)->where('stock', '>', 0)->where('minimum_stock', '>', 0)->whereColumn('stock', '<=', 'minimum_stock');
        $expiring = (clone $activeProducts)
            ->whereDate('expires_at', '>=', $day->toDateString())
            ->whereDate('expires_at', '<=', $day->copy()->addDays(7)->toDateString());
        $expired = (clone $activeProducts)->whereDate('expires_at', '<', $day->toDateString());

        return [
            'date' => $day,
            ...$summary,
            'topProducts' => $topProducts,
            'zeroStockCount' => (clone $zeroStock)->count(),
            'zeroStockProducts' => $zeroStock->orderBy('name')->limit(6)->get(),
            'lowStockCount' => (clone $lowStock)->count(),
            'lowStockProducts' => $lowStock->orderBy('stock')->limit(6)->get(),
            'expiringCount' => (clone $expiring)->count(),
            'expiringProducts' => $expiring->orderBy('expires_at')->limit(6)->get(),
            'expiredCount' => (clone $expired)->count(),
            'expiredProducts' => $expired->orderByDesc('expires_at')->limit(6)->get(),
        ];
    }
}
