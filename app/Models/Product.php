<?php

namespace App\Models;

use App\Enums\MeasurementUnit;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    protected $fillable = [
        'branch_id',
        'category_id',
        'internal_code',
        'barcode',
        'name',
        'unit',
        'purchase_price',
        'sale_price',
        'minimum_stock',
        'expires_at',
        'is_active',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'unit' => MeasurementUnit::class,
            'purchase_price' => 'decimal:2',
            'sale_price' => 'decimal:2',
            'stock' => 'decimal:3',
            'minimum_stock' => 'decimal:3',
            'expires_at' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function inventoryMovements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }

    public function hasLowStock(): bool
    {
        return bccomp($this->minimum_stock, '0', 3) === 1
            && bccomp($this->stock, '0', 3) === 1
            && bccomp($this->stock, $this->minimum_stock, 3) <= 0;
    }

    public function hasZeroStock(): bool
    {
        return bccomp($this->stock, '0', 3) === 0;
    }

    public function displayCode(): string
    {
        return $this->barcode ?: $this->internal_code;
    }
}
