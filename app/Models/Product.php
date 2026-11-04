<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use BelongsToOrganization, HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'metadata' => 'array', 'is_active' => 'boolean', 'min_stock' => 'decimal:3',
            'purchase_price' => 'decimal:2', 'sale_price' => 'decimal:2', 'tax_rate' => 'decimal:2',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function stocks(): HasMany
    {
        return $this->hasMany(Stock::class);
    }

    public function inventoryUnits(): HasMany
    {
        return $this->hasMany(InventoryUnit::class);
    }

    public function inventoryLots(): HasMany
    {
        return $this->hasMany(InventoryLot::class);
    }

    public function localizedName(?string $locale = null): string
    {
        $locale = in_array($locale ?? app()->getLocale(), ['az', 'ru', 'en'], true) ? ($locale ?? app()->getLocale()) : 'az';

        return $this->{'name_'.$locale} ?: $this->name_az;
    }

    public function requiresSerialTracking(): bool
    {
        return $this->tracking_mode === 'serial';
    }

    public function requiresBatchTracking(): bool
    {
        return $this->tracking_mode === 'batch';
    }
}
