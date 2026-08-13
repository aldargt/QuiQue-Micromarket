<?php

namespace App\Services;

use App\Enums\RaffleTicketStatus;
use App\Models\RafflePeriod;
use App\Models\RaffleTicket;
use Carbon\CarbonInterface;

class RafflePeriodService
{
    public function current(int $branchId, CarbonInterface $date): RafflePeriod
    {
        $startMonth = $date->month % 2 === 0 ? $date->month - 1 : $date->month;
        $start = $date->copy()->startOfYear()->month($startMonth)->startOfMonth();
        $end = $start->copy()->addMonth()->endOfMonth();
        $months = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];

        return RafflePeriod::query()->firstOrCreate(
            ['branch_id' => $branchId, 'starts_on' => $start->startOfDay()->toDateTimeString()],
            ['ends_on' => $end->endOfDay()->toDateTimeString(), 'name' => ucfirst($months[$start->month - 1]).' - '.$months[$end->month - 1].' '.$start->year]
        );
    }

    public function expirePastTickets(int $branchId, CarbonInterface $date): void
    {
        RaffleTicket::query()->where('branch_id', $branchId)->where('status', RaffleTicketStatus::Active)
            ->whereHas('period', fn ($query) => $query->whereDate('ends_on', '<', $date->toDateString()))
            ->update(['status' => RaffleTicketStatus::Expired]);
    }
}
