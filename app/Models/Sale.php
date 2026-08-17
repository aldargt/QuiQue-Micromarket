<?php

namespace App\Models;

use App\Enums\SaleStatus;
use Database\Factories\SaleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Sale extends Model
{
    /** @use HasFactory<SaleFactory> */
    use HasFactory;

    protected $fillable = ['branch_id', 'user_id', 'customer_id', 'sale_number', 'subtotal', 'total', 'status', 'confirmed_at', 'cancelled_by', 'cancelled_at', 'cancellation_reason'];

    protected function casts(): array
    {
        return ['subtotal' => 'decimal:2', 'total' => 'decimal:2', 'status' => SaleStatus::class, 'confirmed_at' => 'datetime', 'cancelled_at' => 'datetime'];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function raffleParticipation(): HasOne
    {
        return $this->hasOne(RaffleParticipation::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(PaymentDetail::class);
    }

    public function inventoryMovements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }

    public function paymentLabel(): string
    {
        $methods = $this->payments->pluck('method')->unique();

        return $methods->count() > 1 ? 'Mixto' : ($methods->first()?->label() ?? 'Sin pago');
    }
}
