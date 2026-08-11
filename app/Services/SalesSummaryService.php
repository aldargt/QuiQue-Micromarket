<?php

namespace App\Services;

use App\Enums\PaymentMethod;
use App\Enums\SaleStatus;
use App\Models\PaymentDetail;
use App\Models\Sale;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

class SalesSummaryService
{
    /** @return array{salesCount: int, salesTotal: string, cashTotal: string, cashCount: int, qrTotal: string, qrCount: int, mixedTotal: string, mixedCount: int} */
    public function forPeriod(int $branchId, CarbonInterface $start, CarbonInterface $endExclusive): array
    {
        $sales = Sale::query()->where('branch_id', $branchId)
            ->where('status', SaleStatus::Confirmed->value)
            ->where('confirmed_at', '>=', $start)
            ->where('confirmed_at', '<', $endExclusive);

        $payments = PaymentDetail::query()
            ->join('sales', 'sales.id', '=', 'payment_details.sale_id')
            ->where('sales.branch_id', $branchId)
            ->where('sales.status', SaleStatus::Confirmed->value)
            ->where('sales.confirmed_at', '>=', $start)
            ->where('sales.confirmed_at', '<', $endExclusive)
            ->select('payment_details.method')
            ->selectRaw('SUM(payment_details.amount) as total')
            ->selectRaw('COUNT(DISTINCT sales.id) as operations')
            ->groupBy('payment_details.method')->get()
            ->keyBy(fn ($row) => $row->method instanceof PaymentMethod ? $row->method->value : $row->method);

        $mixed = (clone $sales)
            ->whereHas('payments', fn (Builder $query) => $query->where('method', PaymentMethod::Cash->value))
            ->whereHas('payments', fn (Builder $query) => $query->where('method', PaymentMethod::Qr->value));

        return [
            'salesCount' => (clone $sales)->count(),
            'salesTotal' => (string) ((clone $sales)->sum('total') ?: '0.00'),
            'cashTotal' => (string) ($payments->get(PaymentMethod::Cash->value)?->total ?? '0.00'),
            'cashCount' => (int) ($payments->get(PaymentMethod::Cash->value)?->operations ?? 0),
            'qrTotal' => (string) ($payments->get(PaymentMethod::Qr->value)?->total ?? '0.00'),
            'qrCount' => (int) ($payments->get(PaymentMethod::Qr->value)?->operations ?? 0),
            'mixedTotal' => (string) ((clone $mixed)->sum('total') ?: '0.00'),
            'mixedCount' => (clone $mixed)->count(),
        ];
    }
}
