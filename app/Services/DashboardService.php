<?php

namespace App\Services;

use App\Enums\PaymentMethod;
use App\Enums\SaleStatus;
use App\Models\PaymentDetail;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use Carbon\CarbonInterface;

class DashboardService
{
    /** @return array<string, mixed> */
    public function forBranch(int $branchId, CarbonInterface $day): array
    {
        $start = $day->copy()->startOfDay();
        $end = $day->copy()->endOfDay();
        $sales = Sale::query()->where('branch_id', $branchId)->where('status', SaleStatus::Confirmed->value)->whereBetween('confirmed_at', [$start, $end]);

        $salesCount = (clone $sales)->count();
        $salesTotal = (string) ((clone $sales)->sum('total') ?: '0.00');
        $paymentAmount = fn (PaymentMethod $method): string => (string) (PaymentDetail::query()
            ->join('sales', 'sales.id', '=', 'payment_details.sale_id')
            ->where('sales.branch_id', $branchId)->where('sales.status', SaleStatus::Confirmed->value)
            ->whereBetween('sales.confirmed_at', [$start, $end])->where('payment_details.method', $method->value)
            ->sum('payment_details.amount') ?: '0.00');
        $paymentCount = fn (PaymentMethod $method): int => PaymentDetail::query()
            ->join('sales', 'sales.id', '=', 'payment_details.sale_id')
            ->where('sales.branch_id', $branchId)->where('sales.status', SaleStatus::Confirmed->value)
            ->whereBetween('sales.confirmed_at', [$start, $end])->where('payment_details.method', $method->value)
            ->distinct()->count('sales.id');
        $mixedTotal = (string) ((clone $sales)
            ->whereHas('payments', fn ($query) => $query->where('method', PaymentMethod::Cash->value))
            ->whereHas('payments', fn ($query) => $query->where('method', PaymentMethod::Qr->value))
            ->sum('total') ?: '0.00');
        $mixedCount = (clone $sales)
            ->whereHas('payments', fn ($query) => $query->where('method', PaymentMethod::Cash->value))
            ->whereHas('payments', fn ($query) => $query->where('method', PaymentMethod::Qr->value))
            ->count();

        $topProducts = SaleItem::query()
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->where('sales.branch_id', $branchId)->where('sales.status', SaleStatus::Confirmed->value)
            ->whereBetween('sales.confirmed_at', [$start, $end])
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
            'salesCount' => $salesCount,
            'salesTotal' => $salesTotal,
            'cashTotal' => $paymentAmount(PaymentMethod::Cash),
            'cashCount' => $paymentCount(PaymentMethod::Cash),
            'qrTotal' => $paymentAmount(PaymentMethod::Qr),
            'qrCount' => $paymentCount(PaymentMethod::Qr),
            'mixedTotal' => $mixedTotal,
            'mixedCount' => $mixedCount,
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
