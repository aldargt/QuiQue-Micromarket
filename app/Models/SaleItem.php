<?php

namespace App\Models;

use App\Enums\MeasurementUnit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaleItem extends Model
{
    protected $fillable = ['sale_id', 'product_id', 'product_name', 'unit', 'quantity', 'unit_price', 'subtotal'];

    protected function casts(): array
    {
        return ['unit' => MeasurementUnit::class, 'quantity' => 'decimal:3', 'unit_price' => 'decimal:2', 'subtotal' => 'decimal:2'];
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function quantityLabel(): string
    {
        return ($this->unit ?? $this->product?->unit)?->formatQuantity($this->quantity) ?? $this->quantity;
    }
}
