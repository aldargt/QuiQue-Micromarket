<?php

namespace App\Models;

use App\Enums\RaffleParticipationStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RaffleParticipation extends Model
{
    protected $fillable = ['branch_id', 'sale_id', 'customer_id', 'raffle_period_id', 'decided_by', 'threshold_amount', 'eligible_ticket_count', 'status', 'decided_at'];

    protected function casts(): array
    {
        return ['threshold_amount' => 'decimal:2', 'status' => RaffleParticipationStatus::class, 'decided_at' => 'datetime'];
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(RafflePeriod::class, 'raffle_period_id');
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(RaffleTicket::class);
    }
}
