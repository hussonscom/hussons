<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class InventoryUnit extends Model
{
    use BelongsToOrganization, HasFactory;

    protected $guarded = ['id'];

    protected static function booted(): void
    {
        static::saved(function (InventoryUnit $unit): void {
            if ($unit->wasRecentlyCreated || $unit->wasChanged(['organization_id', 'serial_number', 'imei1', 'imei2'])) {
                $unit->syncIdentifiers();
            }
        });
    }

    public function save(array $options = [])
    {
        $identifiersChanged = ! $this->exists
            || $this->isDirty(['organization_id', 'serial_number', 'imei1', 'imei2']);

        if (! $identifiersChanged) {
            return parent::save($options);
        }

        return $this->getConnection()->transaction(fn () => parent::save($options));
    }

    protected function casts(): array
    {
        return ['purchase_price' => 'decimal:2', 'received_at' => 'datetime', 'sold_at' => 'datetime', 'metadata' => 'array'];
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

    public function saleItem(): BelongsTo
    {
        return $this->belongsTo(SaleItem::class);
    }

    public function identifiers(): HasMany
    {
        return $this->hasMany(InventoryUnitIdentifier::class);
    }

    private function syncIdentifiers(): void
    {
        $values = [
            'serial' => $this->serial_number,
            'imei1' => $this->imei1,
            'imei2' => $this->imei2,
        ];

        DB::table('inventory_unit_identifiers')
            ->where('inventory_unit_id', $this->id)
            ->delete();

        $now = now();
        $rows = [];

        foreach ($values as $kind => $value) {
            if ($value === null || trim((string) $value) === '') {
                continue;
            }

            $rows[] = [
                'organization_id' => $this->organization_id,
                'inventory_unit_id' => $this->id,
                'kind' => $kind,
                'value' => (string) $value,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if ($rows !== []) {
            DB::table('inventory_unit_identifiers')->insert($rows);
        }
    }
}
