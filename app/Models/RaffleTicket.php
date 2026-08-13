<?php

namespace App\Models;

use App\Enums\RaffleTicketStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RaffleTicket extends Model
{
    protected $fillable = ['branch_id', 'raffle_participation_id', 'customer_id', 'sale_id', 'raffle_period_id', 'ticket_number', 'status'];

    protected function casts(): array
    {
        return ['status' => RaffleTicketStatus::class];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(RafflePeriod::class, 'raffle_period_id');
    }
}
