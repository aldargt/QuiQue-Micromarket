<?php

namespace App\Services;

use App\Enums\RaffleParticipationStatus;
use App\Enums\RaffleTicketStatus;
use App\Models\Customer;
use App\Models\RaffleParticipation;
use App\Models\RaffleTicket;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RaffleParticipationService
{
    public function __construct(private RafflePeriodService $periods) {}

    public function offer(Sale $sale, string $threshold): ?RaffleParticipation
    {
        $count = $this->ticketCount($sale->total, $threshold);
        if ($count < 1) {
            return null;
        }

        return $sale->raffleParticipation()->create(['branch_id' => $sale->branch_id, 'threshold_amount' => $threshold, 'eligible_ticket_count' => $count, 'status' => RaffleParticipationStatus::Pending]);
    }

    public function ticketCount(string $total, string $threshold): int
    {
        return bccomp($threshold, '0', 2) === 1 ? (int) bcdiv($total, $threshold, 0) : 0;
    }

    public function decline(User $user, Sale $sale): void
    {
        DB::transaction(function () use ($user, $sale) {
            $participation = $this->pending($user, $sale);
            $participation->update(['status' => RaffleParticipationStatus::Declined, 'decided_by' => $user->id, 'decided_at' => now()]);
        });
    }

    /** @param array{customer_id?:int|null,full_name?:string|null,phone?:string|null,ci?:string|null} $data */
    public function accept(User $user, Sale $sale, array $data): RaffleParticipation
    {
        return DB::transaction(function () use ($user, $sale, $data) {
            $participation = $this->pending($user, $sale);
            $customer = isset($data['customer_id'])
                ? Customer::query()->where('branch_id', $user->branch_id)->find($data['customer_id'])
                : Customer::query()->create(['branch_id' => $user->branch_id, 'full_name' => $data['full_name'], 'phone' => $data['phone'], 'ci' => $data['ci'] ?: null]);
            if (! $customer) {
                throw ValidationException::withMessages(['customer_id' => 'El cliente seleccionado no pertenece a esta sucursal.']);
            }

            $this->periods->expirePastTickets($sale->branch_id, now());
            $period = $this->periods->current($sale->branch_id, $sale->confirmed_at);
            $ticketStatus = $period->ends_on->isBefore(now()->startOfDay()) ? RaffleTicketStatus::Expired : RaffleTicketStatus::Active;
            $participation->update(['customer_id' => $customer->id, 'raffle_period_id' => $period->id, 'status' => RaffleParticipationStatus::Accepted, 'decided_by' => $user->id, 'decided_at' => now()]);
            $sale->update(['customer_id' => $customer->id]);
            for ($number = 0; $number < $participation->eligible_ticket_count; $number++) {
                RaffleTicket::query()->create(['branch_id' => $sale->branch_id, 'raffle_participation_id' => $participation->id, 'customer_id' => $customer->id, 'sale_id' => $sale->id, 'raffle_period_id' => $period->id, 'ticket_number' => 'TKT-'.$period->starts_on->format('Ym').'-'.Str::upper(Str::random(12)), 'status' => $ticketStatus]);
            }

            return $participation->load('tickets');
        });
    }

    private function pending(User $user, Sale $sale): RaffleParticipation
    {
        if ($user->branch_id !== $sale->branch_id) {
            throw new AuthorizationException('No puede gestionar participaciones de otra sucursal.');
        }
        $participation = RaffleParticipation::query()->where('sale_id', $sale->id)->lockForUpdate()->first();
        if (! $participation || $participation->status !== RaffleParticipationStatus::Pending) {
            throw ValidationException::withMessages(['participation' => 'Esta participación ya fue resuelta o no corresponde a la venta.']);
        }

        return $participation;
    }
}
