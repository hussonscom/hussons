<?php

namespace App\Services;

use App\Models\InventoryLot;
use App\Models\InventoryMovement;
use App\Models\InventoryUnit;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Purchase;
use App\Models\Stock;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class InventoryService
{
    public function receive(
        int $organizationId,
        int $branchId,
        Warehouse $warehouse,
        User $user,
        array $data,
    ): Purchase {
        $idempotencyKey = Idempotency::key($data);
        $idempotencyHash = Idempotency::payloadHash([
            'branch_id' => $branchId,
            'warehouse_id' => $warehouse->id,
        ], $data);

        try {
            return DB::transaction(function () use ($organizationId, $branchId, $warehouse, $user, $data, $idempotencyKey, $idempotencyHash) {
                abort_unless((int) $warehouse->organization_id === $organizationId && (int) $warehouse->branch_id === $branchId, 404);

                $existing = Purchase::query()
                    ->where('organization_id', $organizationId)
                    ->where('idempotency_key', $idempotencyKey)
                    ->lockForUpdate()
                    ->first();
                if ($existing) {
                    return $this->replayPurchase($existing, $idempotencyHash);
                }

                if (! empty($data['supplier_id'])) {
                    Supplier::query()->where('organization_id', $organizationId)->findOrFail($data['supplier_id']);
                }

                $purchase = Purchase::query()->create([
                    'organization_id' => $organizationId,
                    'branch_id' => $branchId,
                    'warehouse_id' => $warehouse->id,
                    'supplier_id' => $data['supplier_id'] ?? null,
                    'user_id' => $user->id,
                    'idempotency_key' => $idempotencyKey,
                    'idempotency_hash' => $idempotencyHash,
                    'number' => $this->nextPurchaseNumber($organizationId),
                    'status' => 'draft',
                    'subtotal' => 0,
                    'discount' => 0,
                    'tax' => 0,
                    'total' => 0,
                    'currency' => 'AZN',
                    'notes' => $data['notes'] ?? null,
                    'ordered_at' => $data['received_at'] ?? now(),
                ]);

                $subtotalCents = 0;
                $taxCents = 0;

                foreach ($data['items'] as $position => $line) {
                    $product = Product::query()
                        ->where('organization_id', $organizationId)
                        ->where('is_active', true)
                        ->lockForUpdate()
                        ->findOrFail($line['product_id']);

                    $variant = $this->variant($organizationId, $product, $line['product_variant_id'] ?? null);
                    $quantityMillis = Money::quantityToMillis($line['quantity']);
                    $unitCostCents = Money::toCents($line['unit_cost']);
                    $lineSubtotalCents = Money::lineTotal($unitCostCents, $quantityMillis);
                    $lineTaxCents = Money::tax($lineSubtotalCents, $product->tax_rate);
                    $subtotalCents += $lineSubtotalCents;
                    $taxCents += $lineTaxCents;

                    $purchaseItem = $purchase->items()->create([
                        'product_id' => $product->id,
                        'product_variant_id' => $variant?->id,
                        'name' => $product->name_az,
                        'sku' => $variant?->sku ?? $product->sku,
                        'quantity' => $line['quantity'],
                        'quantity_received' => $line['quantity'],
                        'unit_cost' => Money::fromCents($unitCostCents),
                        'discount' => 0,
                        'tax' => Money::fromCents($lineTaxCents),
                        'total' => Money::fromCents($lineSubtotalCents + $lineTaxCents),
                        'warranty_months' => $line['warranty_months']
                            ?? $variant?->warranty_months
                            ?? data_get($product->metadata, 'warranty_months', 0),
                        'metadata' => ['position' => $position + 1],
                    ]);

                    $lot = null;
                    if ($product->tracking_mode === 'serial') {
                        $this->receiveSerialUnits(
                            $organizationId,
                            $warehouse,
                            $product,
                            $variant,
                            $purchaseItem->id,
                            $quantityMillis,
                            $unitCostCents,
                            $line,
                        );
                    } elseif ($product->tracking_mode === 'batch') {
                        $lot = $this->receiveBatch(
                            $organizationId,
                            $warehouse,
                            $product,
                            $variant,
                            $purchaseItem->id,
                            $quantityMillis,
                            $unitCostCents,
                            $line,
                        );
                    }

                    $this->increaseStock($organizationId, $warehouse->id, $product->id, $variant?->id, $line['quantity']);

                    InventoryMovement::query()->create([
                        'organization_id' => $organizationId,
                        'warehouse_id' => $warehouse->id,
                        'product_id' => $product->id,
                        'product_variant_id' => $variant?->id,
                        'inventory_lot_id' => $lot?->id,
                        'user_id' => $user->id,
                        'type' => 'purchase',
                        'quantity' => $line['quantity'],
                        'unit_cost' => Money::fromCents($unitCostCents),
                        'reference_type' => Purchase::class,
                        'reference_id' => $purchase->id,
                        'notes' => $data['reference'] ?? null,
                        'occurred_at' => $data['received_at'] ?? now(),
                    ]);
                }

                $purchase->update([
                    'status' => 'received',
                    'subtotal' => Money::fromCents($subtotalCents),
                    'tax' => Money::fromCents($taxCents),
                    'total' => Money::fromCents($subtotalCents + $taxCents),
                    'received_at' => $data['received_at'] ?? now(),
                ]);

                app(AuditLogger::class)->record('inventory.received', $purchase, null, [
                    'number' => $purchase->number,
                    'warehouse_id' => $warehouse->id,
                    'total' => Money::fromCents($subtotalCents + $taxCents),
                    'line_count' => count($data['items']),
                ]);

                return $purchase->fresh(['items']);
            }, 3);
        } catch (UniqueConstraintViolationException $exception) {
            $existing = Purchase::query()
                ->where('organization_id', $organizationId)
                ->where('idempotency_key', $idempotencyKey)
                ->first();

            if (! $existing) {
                throw $exception;
            }

            return $this->replayPurchase($existing, $idempotencyHash);
        }
    }

    private function replayPurchase(Purchase $purchase, string $idempotencyHash): Purchase
    {
        Idempotency::assertPayloadMatches($purchase->idempotency_hash, $idempotencyHash);

        return $purchase->fresh(['items']);
    }

    private function variant(int $organizationId, Product $product, ?int $variantId): ?ProductVariant
    {
        if (! $variantId) {
            return null;
        }

        return ProductVariant::query()
            ->where('organization_id', $organizationId)
            ->where('product_id', $product->id)
            ->where('is_active', true)
            ->findOrFail($variantId);
    }

    private function receiveSerialUnits(
        int $organizationId,
        Warehouse $warehouse,
        Product $product,
        ?ProductVariant $variant,
        int $purchaseItemId,
        int $quantityMillis,
        int $unitCostCents,
        array $line,
    ): void {
        if ($quantityMillis % 1000 !== 0) {
            throw ValidationException::withMessages([
                'items' => __('Serial-tracked products must use a whole quantity.'),
            ]);
        }

        $units = array_values($line['units'] ?? []);
        $expected = intdiv($quantityMillis, 1000);
        if (count($units) !== $expected) {
            throw ValidationException::withMessages([
                'items' => __('Provide one serial or IMEI record for every received unit.'),
            ]);
        }

        foreach ($units as $index => $unit) {
            $serialNumber = filled($unit['serial_number'] ?? null) ? trim((string) $unit['serial_number']) : null;
            $imei1 = filled($unit['imei1'] ?? null) ? preg_replace('/\D+/', '', (string) $unit['imei1']) : null;
            $imei2 = filled($unit['imei2'] ?? null) ? preg_replace('/\D+/', '', (string) $unit['imei2']) : null;

            if ($serialNumber === null && $imei1 === null) {
                throw ValidationException::withMessages([
                    "items.units.$index" => __('A serial number or IMEI is required.'),
                ]);
            }

            if ($imei1 !== null && $imei1 === $imei2) {
                throw ValidationException::withMessages([
                    "items.units.$index.imei2" => __('IMEI 1 and IMEI 2 must be different.'),
                ]);
            }

            $serialExists = $serialNumber !== null && InventoryUnit::query()
                ->where('organization_id', $organizationId)
                ->where('serial_number', $serialNumber)
                ->exists();
            $imeis = array_values(array_filter([$imei1, $imei2]));
            $imeiExists = $imeis !== [] && InventoryUnit::query()
                ->where('organization_id', $organizationId)
                ->where(function ($query) use ($imeis): void {
                    $query->whereIn('imei1', $imeis)->orWhereIn('imei2', $imeis);
                })
                ->exists();

            if ($serialExists || $imeiExists) {
                throw ValidationException::withMessages([
                    "items.units.$index" => __('This serial number or IMEI is already registered.'),
                ]);
            }

            InventoryUnit::query()->create([
                'organization_id' => $organizationId,
                'warehouse_id' => $warehouse->id,
                'product_id' => $product->id,
                'product_variant_id' => $variant?->id,
                'purchase_item_id' => $purchaseItemId,
                'serial_number' => $serialNumber,
                'imei1' => $imei1,
                'imei2' => $imei2,
                'purchase_price' => Money::fromCents($unitCostCents),
                'warranty_months' => $unit['warranty_months']
                    ?? $line['warranty_months']
                    ?? $variant?->warranty_months
                    ?? data_get($product->metadata, 'warranty_months', 0),
                'status' => 'in_stock',
                'received_at' => now(),
                'metadata' => [],
            ]);
        }
    }

    private function receiveBatch(
        int $organizationId,
        Warehouse $warehouse,
        Product $product,
        ?ProductVariant $variant,
        int $purchaseItemId,
        int $quantityMillis,
        int $unitCostCents,
        array $line,
    ): InventoryLot {
        if (blank($line['batch_number'] ?? null) || blank($line['expiry_date'] ?? null)) {
            throw ValidationException::withMessages([
                'items' => __('Batch number and expiry date are required for batch-tracked products.'),
            ]);
        }

        $expiryDate = CarbonImmutable::parse($line['expiry_date'])->startOfDay();
        if ($expiryDate->isBefore(today())) {
            throw ValidationException::withMessages([
                'items' => __('Expired stock cannot be received.'),
            ]);
        }

        $lot = InventoryLot::query()
            ->where('organization_id', $organizationId)
            ->where('warehouse_id', $warehouse->id)
            ->where('product_id', $product->id)
            ->where('product_variant_id', $variant?->id)
            ->where('batch_number', $line['batch_number'])
            ->lockForUpdate()
            ->first();

        $quantity = number_format($quantityMillis / 1000, 3, '.', '');
        if ($lot) {
            if (! $lot->expiry_date->isSameDay($expiryDate)) {
                throw ValidationException::withMessages([
                    'items' => __('A batch with this number already has a different expiry date.'),
                ]);
            }

            $lot->update([
                'quantity_received' => (float) $lot->quantity_received + (float) $quantity,
                'quantity_available' => (float) $lot->quantity_available + (float) $quantity,
                'purchase_price' => Money::fromCents($unitCostCents),
                'sale_price' => $line['sale_price'] ?? $variant?->sale_price ?? $product->sale_price,
                'status' => 'active',
            ]);

            return $lot->fresh();
        }

        return InventoryLot::query()->create([
            'organization_id' => $organizationId,
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'product_variant_id' => $variant?->id,
            'purchase_item_id' => $purchaseItemId,
            'batch_number' => $line['batch_number'],
            'expiry_date' => $expiryDate,
            'quantity_received' => $quantity,
            'quantity_available' => $quantity,
            'purchase_price' => Money::fromCents($unitCostCents),
            'sale_price' => $line['sale_price'] ?? $variant?->sale_price ?? $product->sale_price,
            'status' => 'active',
        ]);
    }

    private function increaseStock(int $organizationId, int $warehouseId, int $productId, ?int $variantId, mixed $quantity): void
    {
        $stock = Stock::query()
            ->where('organization_id', $organizationId)
            ->where('warehouse_id', $warehouseId)
            ->where('product_id', $productId)
            ->where('product_variant_id', $variantId)
            ->lockForUpdate()
            ->first();

        if ($stock) {
            $stock->increment('quantity', $quantity);

            return;
        }

        Stock::query()->create([
            'organization_id' => $organizationId,
            'warehouse_id' => $warehouseId,
            'product_id' => $productId,
            'product_variant_id' => $variantId,
            'quantity' => $quantity,
            'reserved_quantity' => 0,
        ]);
    }

    private function nextPurchaseNumber(int $organizationId): string
    {
        do {
            $number = 'PO-'.now()->format('ymd').'-'.Str::upper(Str::random(6));
        } while (Purchase::query()->where('organization_id', $organizationId)->where('number', $number)->exists());

        return $number;
    }
}
