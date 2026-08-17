<?php

namespace App\Services;

use App\Enums\RaffleParticipationStatus;
use App\Enums\RaffleTicketStatus;
use App\Enums\RoleSlug;
use App\Enums\SaleStatus;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaleCancellationService
{
    public function __construct(private InventoryService $inventory, private AuditService $audit) {}

    public function cancel(User $user, Sale $sale, string $reason): Sale
    {
        return DB::transaction(function () use ($user, $sale, $reason): Sale {
            if (! $user->hasAnyRole([RoleSlug::Administrator->value, RoleSlug::Cashier->value]) || $user->branch_id !== $sale->branch_id) {
                throw new AuthorizationException('No puede anular esta venta.');
            }

            $lockedSale = Sale::query()->whereKey($sale->id)->lockForUpdate()->firstOrFail();
            if ($lockedSale->status !== SaleStatus::Confirmed) {
                throw ValidationException::withMessages(['sale' => 'Esta venta ya fue anulada y no puede procesarse nuevamente.']);
            }
            if (! $lockedSale->confirmed_at->timezone(config('app.timezone'))->isSameDay(now(config('app.timezone')))) {
                throw ValidationException::withMessages(['sale' => 'Esta venta ya no puede ser anulada porque corresponde a una fecha anterior.']);
            }

            $items = $lockedSale->items()->orderBy('product_id')->get();
            $products = Product::query()->whereIn('id', $items->pluck('product_id'))->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            if ($products->count() !== $items->pluck('product_id')->unique()->count()) {
                throw ValidationException::withMessages(['sale' => 'No fue posible localizar todos los productos de la venta.']);
            }

            foreach ($items as $item) {
                $this->inventory->recordSaleReversal($user, $products->get($item->product_id), $lockedSale, $item->quantity, 'Anulación de venta '.$lockedSale->sale_number, $reason);
            }

            if ($participation = $lockedSale->raffleParticipation()->lockForUpdate()->first()) {
                $participation->update(['status' => RaffleParticipationStatus::Cancelled]);
                $participation->tickets()->update(['status' => RaffleTicketStatus::Cancelled]);
            }

            $cancelledAt = now();
            $lockedSale->update([
                'status' => SaleStatus::Cancelled,
                'cancelled_by' => $user->id,
                'cancelled_at' => $cancelledAt,
                'cancellation_reason' => $reason,
            ]);
            $this->audit->record($user, 'Venta anulada', $lockedSale,
                ['status' => SaleStatus::Confirmed->value],
                ['status' => SaleStatus::Cancelled->value, 'reason' => $reason]);

            return $lockedSale->refresh();
        });
    }
}
