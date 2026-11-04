<?php

namespace App\Services;

use App\Contracts\FiscalProvider;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\FiscalReceipt;
use App\Models\InventoryLot;
use App\Models\InventoryMovement;
use App\Models\InventoryUnit;
use App\Models\Prescription;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Stock;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SaleService
{
    private const DISCOUNT_ROLES = ['owner', 'admin', 'branch_manager'];

    public function __construct(private readonly FiscalProvider $fiscalProvider) {}

    public function checkout(
        int $organizationId,
        Branch $branch,
        Warehouse $warehouse,
        User $user,
        array $data,
        ?string $membershipRole = null,
    ): Sale {
        $idempotencyKey = Idempotency::key($data);
        $idempotencyHash = Idempotency::payloadHash([
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
        ], $data);

        try {
            return DB::transaction(function () use ($organizationId, $branch, $warehouse, $user, $data, $membershipRole, $idempotencyKey, $idempotencyHash) {
                abort_unless((int) $branch->organization_id === $organizationId, 404);
                abort_unless((int) $warehouse->organization_id === $organizationId && (int) $warehouse->branch_id === (int) $branch->id, 404);

                $existing = Sale::query()
                    ->where('organization_id', $organizationId)
                    ->where('idempotency_key', $idempotencyKey)
                    ->lockForUpdate()
                    ->first();
                if ($existing) {
                    return $this->replaySale($existing, $idempotencyHash);
                }

                $globalDiscountCents = Money::toCents($data['discount'] ?? 0);
                $this->validateDiscount($globalDiscountCents, $membershipRole, 'discount');
                foreach ($data['items'] as $lineIndex => $line) {
                    $this->validateDiscount(
                        Money::toCents($line['discount'] ?? 0),
                        $membershipRole,
                        "items.$lineIndex.discount",
                    );
                }

                if (! empty($data['customer_id'])) {
                    Customer::query()->where('organization_id', $organizationId)->findOrFail($data['customer_id']);
                }

                $sale = Sale::query()->create([
                    'organization_id' => $organizationId,
                    'branch_id' => $branch->id,
                    'warehouse_id' => $warehouse->id,
                    'customer_id' => $data['customer_id'] ?? null,
                    'user_id' => $user->id,
                    'cash_shift_id' => $data['cash_shift_id'] ?? null,
                    'idempotency_key' => $idempotencyKey,
                    'idempotency_hash' => $idempotencyHash,
                    'number' => $this->nextSaleNumber($organizationId),
                    'status' => 'draft',
                    'subtotal' => 0,
                    'discount' => 0,
                    'tax' => 0,
                    'total' => 0,
                    'currency' => 'AZN',
                    'notes' => $data['notes'] ?? null,
                    'sold_at' => now(),
                ]);

                $subtotalCents = 0;
                $lineDiscountCents = 0;
                $requiresPrescription = false;

                foreach ($data['items'] as $line) {
                    $product = Product::query()
                        ->where('organization_id', $organizationId)
                        ->where('is_active', true)
                        ->lockForUpdate()
                        ->findOrFail($line['product_id']);
                    $variant = $this->variant($organizationId, $product, $line['product_variant_id'] ?? null);
                    $quantityMillis = Money::quantityToMillis($line['quantity']);
                    $configuredPrice = $variant?->sale_price ?? $product->sale_price;
                    $unitPriceCents = in_array($membershipRole, ['owner', 'admin', 'manager', 'branch_manager'], true)
                        ? Money::toCents($line['unit_price'])
                        : Money::toCents($configuredPrice);
                    $requestedDiscountCents = Money::toCents($line['discount'] ?? 0);
                    $grossCents = Money::lineTotal($unitPriceCents, $quantityMillis);

                    if ($requestedDiscountCents > $grossCents) {
                        throw ValidationException::withMessages([
                            'items' => __('A line discount cannot exceed its line value.'),
                        ]);
                    }

                    $subtotalCents += $grossCents;
                    $lineDiscountCents += $requestedDiscountCents;
                    $requiresPrescription = $requiresPrescription || $this->requiresPrescription($product);

                    $allocations = match ($product->tracking_mode) {
                        'serial' => $this->serialAllocations($organizationId, $warehouse, $product, $variant, $line, $quantityMillis),
                        'batch' => $this->batchAllocations($organizationId, $warehouse, $product, $variant, $line, $quantityMillis),
                        default => [[
                            'quantity_millis' => $quantityMillis,
                            'cost_cents' => Money::toCents($variant?->purchase_price ?? $product->purchase_price),
                            'unit' => null,
                            'lot' => null,
                        ]],
                    };

                    $allocationDiscounts = $this->allocateProportionally(
                        $requestedDiscountCents,
                        array_map(
                            fn (array $allocation): int => Money::lineTotal($unitPriceCents, $allocation['quantity_millis']),
                            $allocations,
                        ),
                    );
                    foreach ($allocations as $index => $allocation) {
                        $item = $this->createItem(
                            $sale,
                            $product,
                            $variant,
                            $allocation,
                            $unitPriceCents,
                            $allocationDiscounts[$index],
                        );

                        if ($allocation['unit']) {
                            $allocation['unit']->update([
                                'sale_item_id' => $item->id,
                                'status' => 'sold',
                                'sold_at' => now(),
                            ]);
                        }

                        if ($allocation['lot']) {
                            $remaining = (float) $allocation['lot']->quantity_available - ($allocation['quantity_millis'] / 1000);
                            $allocation['lot']->update([
                                'quantity_available' => $remaining,
                                'status' => $remaining <= 0 ? 'depleted' : 'active',
                            ]);
                        }

                        InventoryMovement::query()->create([
                            'organization_id' => $organizationId,
                            'warehouse_id' => $warehouse->id,
                            'product_id' => $product->id,
                            'product_variant_id' => $variant?->id,
                            'inventory_unit_id' => $allocation['unit']?->id,
                            'inventory_lot_id' => $allocation['lot']?->id,
                            'user_id' => $user->id,
                            'type' => 'sale',
                            'quantity' => -($allocation['quantity_millis'] / 1000),
                            'unit_cost' => Money::fromCents($allocation['cost_cents']),
                            'reference_type' => Sale::class,
                            'reference_id' => $sale->id,
                            'occurred_at' => now(),
                        ]);
                    }

                    $this->decreaseStock($organizationId, $warehouse->id, $product->id, $variant?->id, $quantityMillis);
                }

                $discountCents = $globalDiscountCents + $lineDiscountCents;
                if ($discountCents > $subtotalCents) {
                    throw ValidationException::withMessages([
                        'discount' => __('The discount cannot exceed the sale subtotal.'),
                    ]);
                }

                [$taxCents, $itemsTotalCents] = $this->applyGlobalDiscount($sale, $globalDiscountCents);
                $expectedTotalCents = $subtotalCents - $discountCents + $taxCents;
                if ($itemsTotalCents !== $expectedTotalCents) {
                    throw new \LogicException('Sale item totals do not reconcile with the sale total.');
                }

                if ($requiresPrescription) {
                    $this->recordPrescription($sale, $organizationId, $branch->id, $user, $data, $membershipRole);
                }

                $totalCents = $itemsTotalCents;
                $paidCents = collect($data['payments'])->sum(fn ($payment) => Money::toCents($payment['amount']));
                if ($paidCents < $totalCents) {
                    throw ValidationException::withMessages([
                        'payments' => __('Payment total is less than the amount due.'),
                    ]);
                }

                foreach ($data['payments'] as $payment) {
                    $sale->payments()->create([
                        'user_id' => $user->id,
                        'method' => $payment['method'],
                        'amount' => Money::fromCents(Money::toCents($payment['amount'])),
                        'currency' => 'AZN',
                        'reference' => $payment['reference'] ?? null,
                        'paid_at' => now(),
                        'metadata' => [],
                    ]);
                }

                $sale->update([
                    'status' => 'completed',
                    'subtotal' => Money::fromCents($subtotalCents),
                    'discount' => Money::fromCents($discountCents),
                    'tax' => Money::fromCents($taxCents),
                    'total' => Money::fromCents($totalCents),
                ]);

                $sale = $sale->fresh(['items.product', 'payments', 'customer']);
                $receipt = $this->recordFiscalOperation($sale, 'issue');

                app(AuditLogger::class)->record('sale.completed', $sale, null, [
                    'number' => $sale->number,
                    'total' => Money::fromCents($totalCents),
                    'payment_count' => count($data['payments']),
                    'item_count' => count($data['items']),
                    'fiscal_receipt_id' => $receipt->id,
                ]);

                return $sale->fresh(['items.product', 'payments', 'fiscalReceipts', 'customer']);
            }, 3);
        } catch (UniqueConstraintViolationException $exception) {
            $existing = Sale::query()
                ->where('organization_id', $organizationId)
                ->where('idempotency_key', $idempotencyKey)
                ->first();

            if (! $existing) {
                throw $exception;
            }

            return $this->replaySale($existing, $idempotencyHash);
        }
    }

    public function refund(int $organizationId, Sale $sale, User $user, ?string $reason = null): Sale
    {
        return DB::transaction(function () use ($organizationId, $sale, $user, $reason) {
            $sale = Sale::query()
                ->where('organization_id', $organizationId)
                ->with(['items', 'payments'])
                ->lockForUpdate()
                ->findOrFail($sale->id);

            if ($sale->status !== 'completed') {
                throw ValidationException::withMessages([
                    'sale' => __('Only completed sales can be refunded.'),
                ]);
            }

            foreach ($sale->items as $item) {
                $quantityMillis = Money::quantityToMillis($item->quantity);
                $movementQuantity = $item->quantity;

                if ($item->inventory_unit_id) {
                    $unit = InventoryUnit::query()
                        ->where('organization_id', $organizationId)
                        ->whereKey($item->inventory_unit_id)
                        ->lockForUpdate()
                        ->firstOrFail();
                    $metadata = $unit->metadata ?? [];
                    $metadata['return'] = [
                        'sale_id' => $sale->id,
                        'received_at' => now()->toIso8601String(),
                        'received_by' => $user->id,
                        'reason' => $reason,
                    ];
                    $unit->update([
                        'status' => 'returned',
                        'sold_at' => null,
                        'metadata' => $metadata,
                    ]);
                    // A serialized return is quarantined until warehouse inspection.
                    $movementQuantity = 0;
                } elseif ($item->inventory_lot_id) {
                    $lot = InventoryLot::query()
                        ->where('organization_id', $organizationId)
                        ->whereKey($item->inventory_lot_id)
                        ->lockForUpdate()
                        ->firstOrFail();
                    $lot->update([
                        'quantity_available' => (float) $lot->quantity_available + ($quantityMillis / 1000),
                        'status' => $lot->isExpired() ? 'expired' : 'active',
                    ]);
                    if ($lot->isExpired()) {
                        $movementQuantity = 0;
                    } else {
                        $this->increaseStock($organizationId, $sale->warehouse_id, $item->product_id, $item->product_variant_id, $item->quantity);
                    }
                } else {
                    $this->increaseStock($organizationId, $sale->warehouse_id, $item->product_id, $item->product_variant_id, $item->quantity);
                }

                InventoryMovement::query()->create([
                    'organization_id' => $organizationId,
                    'warehouse_id' => $sale->warehouse_id,
                    'product_id' => $item->product_id,
                    'product_variant_id' => $item->product_variant_id,
                    'inventory_unit_id' => $item->inventory_unit_id,
                    'inventory_lot_id' => $item->inventory_lot_id,
                    'user_id' => $user->id,
                    'type' => 'sale_return',
                    'quantity' => $movementQuantity,
                    'unit_cost' => $item->cost,
                    'reference_type' => Sale::class,
                    'reference_id' => $sale->id,
                    'notes' => $reason,
                    'occurred_at' => now(),
                ]);
            }

            $before = $sale->toArray();
            $refundCents = 0;
            $refundedPaymentCount = 0;
            foreach ($sale->payments->filter(fn ($payment): bool => Money::toCents($payment->amount) > 0) as $payment) {
                $paymentCents = Money::toCents($payment->amount);
                $sale->payments()->create([
                    'user_id' => $user->id,
                    'method' => $payment->method,
                    'amount' => Money::fromCents(-$paymentCents),
                    'currency' => $payment->currency,
                    'reference' => $payment->reference,
                    'paid_at' => now(),
                    'metadata' => [
                        'type' => 'refund',
                        'reverses_payment_id' => $payment->id,
                        'reason' => $reason,
                    ],
                ]);
                $refundCents += $paymentCents;
                $refundedPaymentCount++;
            }

            if ($refundedPaymentCount === 0) {
                throw new \LogicException('A completed sale must have a payment to refund.');
            }

            $sale->update(['status' => 'refunded', 'notes' => trim($sale->notes."\n".$reason)]);
            $sale = $sale->fresh(['items', 'payments']);
            $netPaymentCents = $sale->payments->sum(fn ($payment): int => Money::toCents($payment->amount));
            if ($netPaymentCents !== 0) {
                throw new \LogicException('Refund payments do not reconcile with the original payments.');
            }
            $receipt = $this->recordFiscalOperation($sale, 'refund', $reason);
            app(AuditLogger::class)->record('sale.refunded', $sale, $before, [
                'status' => 'refunded',
                'reason' => $reason,
                'refund_amount' => Money::fromCents($refundCents),
                'refunded_payment_count' => $refundedPaymentCount,
                'fiscal_receipt_id' => $receipt->id,
            ]);

            return $sale->fresh(['items', 'payments', 'fiscalReceipts']);
        }, 3);
    }

    private function validateDiscount(int $discountCents, ?string $membershipRole, string $field): void
    {
        if ($discountCents < 0) {
            throw ValidationException::withMessages([$field => __('A discount cannot be negative.')]);
        }

        if ($discountCents > 0 && ! in_array($membershipRole, self::DISCOUNT_ROLES, true)) {
            throw ValidationException::withMessages([
                $field => __('Only an owner, administrator or branch manager may apply discounts.'),
            ]);
        }
    }

    private function replaySale(Sale $sale, string $idempotencyHash): Sale
    {
        Idempotency::assertPayloadMatches($sale->idempotency_hash, $idempotencyHash);

        return $sale->fresh(['items.product', 'payments', 'fiscalReceipts', 'customer']);
    }

    /**
     * @param  list<int>  $weights
     * @return list<int>
     */
    private function allocateProportionally(int $amountCents, array $weights): array
    {
        if ($amountCents === 0) {
            return array_fill(0, count($weights), 0);
        }

        $totalWeight = array_sum($weights);
        if ($amountCents < 0 || $amountCents > $totalWeight || $totalWeight <= 0) {
            throw new \LogicException('A discount cannot exceed its allocation base.');
        }

        $remainingAmount = $amountCents;
        $remainingWeight = $totalWeight;
        $lastIndex = array_key_last($weights);
        $allocations = [];

        foreach ($weights as $index => $weight) {
            if ($index === $lastIndex || $remainingAmount === $remainingWeight) {
                $allocation = $remainingAmount;
            } elseif ($weight <= 0 || $remainingAmount === 0) {
                $allocation = 0;
            } else {
                $allocation = (int) floor($remainingAmount * ($weight / $remainingWeight));
            }

            $allocation = min($allocation, $weight);
            $allocations[] = $allocation;
            $remainingAmount -= $allocation;
            $remainingWeight -= $weight;
        }

        if ($remainingAmount !== 0) {
            throw new \LogicException('The discount could not be fully allocated.');
        }

        return $allocations;
    }

    /** @return array{int, int} Tax cents and total cents. */
    private function applyGlobalDiscount(Sale $sale, int $globalDiscountCents): array
    {
        $items = $sale->items()->with('product')->orderBy('id')->get();
        $grossByItem = [];
        $eligibleByItem = [];

        foreach ($items as $item) {
            $grossCents = Money::lineTotal(
                Money::toCents($item->unit_price),
                Money::quantityToMillis($item->quantity),
            );
            $lineDiscountCents = Money::toCents($item->discount);
            $grossByItem[] = $grossCents;
            $eligibleByItem[] = $grossCents - $lineDiscountCents;
        }

        $globalAllocations = $this->allocateProportionally($globalDiscountCents, $eligibleByItem);
        $taxCents = 0;
        $totalCents = 0;

        foreach ($items as $index => $item) {
            $discountCents = Money::toCents($item->discount) + $globalAllocations[$index];
            $taxableCents = $grossByItem[$index] - $discountCents;
            $itemTaxCents = Money::tax($taxableCents, $item->product->tax_rate);
            $itemTotalCents = $taxableCents + $itemTaxCents;

            $item->update([
                'discount' => Money::fromCents($discountCents),
                'tax' => Money::fromCents($itemTaxCents),
                'total' => Money::fromCents($itemTotalCents),
            ]);

            $taxCents += $itemTaxCents;
            $totalCents += $itemTotalCents;
        }

        return [$taxCents, $totalCents];
    }

    private function recordFiscalOperation(Sale $sale, string $operation, ?string $reason = null): FiscalReceipt
    {
        if (! in_array($operation, ['issue', 'refund'], true)) {
            throw new \InvalidArgumentException('Unsupported fiscal operation.');
        }

        $sale->unsetRelation('items')->unsetRelation('payments');
        $sale->load(['items', 'payments']);
        $requestPayload = [
            'operation' => $operation,
            'sale_number' => $sale->number,
            'status' => $sale->status,
            'subtotal' => $sale->subtotal,
            'discount' => $sale->discount,
            'tax' => $sale->tax,
            'total' => $sale->total,
            'currency' => $sale->currency,
            'reason' => $reason,
            'payments' => $sale->payments->map(fn ($payment): array => [
                'id' => $payment->id,
                'method' => $payment->method,
                'amount' => $payment->amount,
                'currency' => $payment->currency,
                'reference' => $payment->reference,
                'type' => data_get($payment->metadata, 'type', 'payment'),
            ])->values()->all(),
        ];

        $result = $operation === 'issue'
            ? $this->fiscalProvider->issue($sale)
            : $this->fiscalProvider->refund($sale);
        $responsePayload = (array) ($result['payload'] ?? []);

        return FiscalReceipt::query()->create([
            'organization_id' => $sale->organization_id,
            'sale_id' => $sale->id,
            'operation' => $operation,
            'provider' => $this->fiscalProvider->name(),
            'status' => (string) ($result['status'] ?? 'pending'),
            'external_id' => $result['external_id'] ?? null,
            'fiscal_number' => $responsePayload['fiscal_number']
                ?? $responsePayload['receipt_number']
                ?? $result['external_id']
                ?? ($operation === 'refund' ? $sale->number.'-R' : $sale->number),
            'request_payload' => $requestPayload,
            'response_payload' => $responsePayload,
            'issued_at' => now(),
        ]);
    }

    private function serialAllocations(
        int $organizationId,
        Warehouse $warehouse,
        Product $product,
        ?ProductVariant $variant,
        array $line,
        int $quantityMillis,
    ): array {
        if ($quantityMillis % 1000 !== 0) {
            throw ValidationException::withMessages(['items' => __('Serial-tracked products require a whole quantity.')]);
        }

        $ids = array_values(array_unique($line['inventory_unit_ids'] ?? []));
        if (count($ids) !== intdiv($quantityMillis, 1000)) {
            throw ValidationException::withMessages(['items' => __('Select each serial-tracked unit being sold.')]);
        }

        $units = InventoryUnit::query()
            ->where('organization_id', $organizationId)
            ->where('warehouse_id', $warehouse->id)
            ->where('product_id', $product->id)
            ->where('product_variant_id', $variant?->id)
            ->where('status', 'in_stock')
            ->whereIn('id', $ids)
            ->lockForUpdate()
            ->get();

        if ($units->count() !== count($ids)) {
            throw ValidationException::withMessages(['items' => __('One or more selected serial numbers are unavailable.')]);
        }

        return $units->map(fn ($unit) => [
            'quantity_millis' => 1000,
            'cost_cents' => Money::toCents($unit->purchase_price),
            'unit' => $unit,
            'lot' => null,
        ])->all();
    }

    private function batchAllocations(
        int $organizationId,
        Warehouse $warehouse,
        Product $product,
        ?ProductVariant $variant,
        array $line,
        int $quantityMillis,
    ): array {
        $query = InventoryLot::query()
            ->where('organization_id', $organizationId)
            ->where('warehouse_id', $warehouse->id)
            ->where('product_id', $product->id)
            ->where('product_variant_id', $variant?->id)
            ->where('status', 'active')
            ->whereDate('expiry_date', '>=', today())
            ->where('quantity_available', '>', 0);

        $requestedLotId = $line['inventory_lot_id'] ?? null;
        if ($product->type === 'drug' && $requestedLotId) {
            $firstFefoLotId = (clone $query)
                ->orderBy('expiry_date')
                ->orderBy('id')
                ->value('id');

            if ((int) $requestedLotId !== (int) $firstFefoLotId) {
                throw ValidationException::withMessages([
                    'items' => __('Drug lots must be allocated in FEFO order.'),
                ]);
            }
        } elseif ($requestedLotId) {
            $query->whereKey($requestedLotId);
        }

        $query
            ->orderBy('expiry_date')
            ->orderBy('id')
            ->lockForUpdate();

        $lots = $query->get();
        $remaining = $quantityMillis;
        $allocations = [];
        foreach ($lots as $lot) {
            $available = Money::quantityToMillis($lot->quantity_available);
            $take = min($remaining, $available);
            if ($take <= 0) {
                continue;
            }

            $allocations[] = [
                'quantity_millis' => $take,
                'cost_cents' => Money::toCents($lot->purchase_price),
                'unit' => null,
                'lot' => $lot,
            ];
            $remaining -= $take;

            if ($remaining === 0) {
                break;
            }
        }

        if ($remaining > 0) {
            throw ValidationException::withMessages([
                'items' => __('There is not enough non-expired stock for this batch-tracked product.'),
            ]);
        }

        return $allocations;
    }

    private function createItem(
        Sale $sale,
        Product $product,
        ?ProductVariant $variant,
        array $allocation,
        int $unitPriceCents,
        int $discountCents,
    ): SaleItem {
        $grossCents = Money::lineTotal($unitPriceCents, $allocation['quantity_millis']);
        $taxableCents = max(0, $grossCents - $discountCents);
        $taxCents = Money::tax($taxableCents, $product->tax_rate);

        return $sale->items()->create([
            'product_id' => $product->id,
            'product_variant_id' => $variant?->id,
            'inventory_unit_id' => $allocation['unit']?->id,
            'inventory_lot_id' => $allocation['lot']?->id,
            'name' => $product->name_az,
            'sku' => $variant?->sku ?? $product->sku,
            'quantity' => number_format($allocation['quantity_millis'] / 1000, 3, '.', ''),
            'unit_price' => Money::fromCents($unitPriceCents),
            'discount' => Money::fromCents($discountCents),
            'tax' => Money::fromCents($taxCents),
            'total' => Money::fromCents($taxableCents + $taxCents),
            'cost' => Money::fromCents($allocation['cost_cents']),
            'warranty_months' => $allocation['unit']?->warranty_months
                ?? $variant?->warranty_months
                ?? data_get($product->metadata, 'warranty_months', 0),
            'metadata' => [],
        ]);
    }

    private function decreaseStock(int $organizationId, int $warehouseId, int $productId, ?int $variantId, int $quantityMillis): void
    {
        $stock = Stock::query()
            ->where('organization_id', $organizationId)
            ->where('warehouse_id', $warehouseId)
            ->where('product_id', $productId)
            ->where('product_variant_id', $variantId)
            ->lockForUpdate()
            ->first();
        $availableMillis = $stock
            ? Money::quantityToMillis((float) $stock->quantity - (float) $stock->reserved_quantity)
            : 0;

        if (! $stock || $availableMillis < $quantityMillis) {
            throw ValidationException::withMessages(['items' => __('Insufficient stock for one or more products.')]);
        }

        $stock->decrement('quantity', $quantityMillis / 1000);
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
        } else {
            Stock::query()->create([
                'organization_id' => $organizationId,
                'warehouse_id' => $warehouseId,
                'product_id' => $productId,
                'product_variant_id' => $variantId,
                'quantity' => $quantity,
                'reserved_quantity' => 0,
            ]);
        }
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

    private function requiresPrescription(Product $product): bool
    {
        return $product->type === 'drug' && (bool) data_get($product->metadata, 'prescription_required', false);
    }

    private function recordPrescription(
        Sale $sale,
        int $organizationId,
        int $branchId,
        User $user,
        array $data,
        ?string $membershipRole,
    ): void {
        $prescription = $data['prescription'] ?? [];
        if (blank($prescription['number'] ?? null)) {
            throw ValidationException::withMessages([
                'prescription.number' => __('A prescription number is required for this sale.'),
            ]);
        }

        if (! in_array($membershipRole, ['owner', 'admin', 'pharmacist'], true)) {
            throw ValidationException::withMessages([
                'prescription' => __('A pharmacist or administrator must verify prescription sales.'),
            ]);
        }

        Prescription::query()->create([
            'organization_id' => $organizationId,
            'branch_id' => $branchId,
            'sale_id' => $sale->id,
            'customer_id' => $sale->customer_id,
            'number' => $prescription['number'],
            'prescriber_name' => $prescription['prescriber'] ?? null,
            'issued_at' => $prescription['issued_at'] ?? now(),
            'verified_by' => $user->id,
            'verified_at' => now(),
            'notes' => $prescription['notes'] ?? null,
            'metadata' => [],
        ]);
    }

    private function nextSaleNumber(int $organizationId): string
    {
        do {
            $number = 'ONE-'.now()->format('ymd').'-'.Str::upper(Str::random(6));
        } while (Sale::query()->where('organization_id', $organizationId)->where('number', $number)->exists());

        return $number;
    }
}
