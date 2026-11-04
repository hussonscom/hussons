<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Stock extends Model
{
    use BelongsToOrganization, HasFactory;

    protected $guarded = ['id'];

    protected static function booted(): void
    {
        static::saving(function (Stock $stock): void {
            $stock->variant_key = $stock->product_variant_id ?? 0;
        });
    }

    protected function casts(): array
    {
        return [
            'variant_key' => 'integer',
            'quantity' => 'decimal:3',
            'reserved_quantity' => 'decimal:3',
        ];
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function getAvailableQuantityAttribute(): float
    {
        return round((float) $this->quantity - (float) $this->reserved_quantity, 3);
    }
}
