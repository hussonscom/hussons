<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryLot extends Model
{
    use BelongsToOrganization, HasFactory;

    protected $guarded = ['id'];

    protected static function booted(): void
    {
        static::saving(function (InventoryLot $lot): void {
            $lot->variant_key = $lot->product_variant_id ?? 0;
        });
    }

    protected function casts(): array
    {
        return [
            'variant_key' => 'integer',
            'expiry_date' => 'date', 'quantity_received' => 'decimal:3', 'quantity_available' => 'decimal:3',
            'purchase_price' => 'decimal:2', 'sale_price' => 'decimal:2',
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

    public function purchaseItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseItem::class);
    }

    public function scopeSellable(Builder $query): Builder
    {
        return $query->where('status', 'active')->where('quantity_available', '>', 0)
            ->where(fn (Builder $q) => $q->whereNull('expiry_date')->orWhereDate('expiry_date', '>=', today()))
            ->orderByRaw('CASE WHEN expiry_date IS NULL THEN 1 ELSE 0 END')->orderBy('expiry_date');
    }

    public function isExpired(): bool
    {
        return $this->expiry_date?->isBefore(today()) ?? false;
    }
}
