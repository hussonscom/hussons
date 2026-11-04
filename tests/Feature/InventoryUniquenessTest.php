<?php

namespace Tests\Feature;

use App\Models\InventoryLot;
use App\Models\ProductVariant;
use App\Models\Stock;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\CreatesWorkspace;
use Tests\TestCase;

class InventoryUniquenessTest extends TestCase
{
    use CreatesWorkspace;
    use RefreshDatabase;

    public function test_inventory_models_use_a_non_null_key_for_base_and_variant_records(): void
    {
        $workspace = $this->createWorkspace();
        $product = $this->createProduct($workspace['organization']);
        $variant = ProductVariant::query()->create([
            'organization_id' => $workspace['organization']->id,
            'product_id' => $product->id,
            'sku' => 'VARIANT-KEY-1',
            'attributes' => [],
            'warranty_months' => 0,
            'is_active' => true,
        ]);

        $baseStock = Stock::query()->create([
            'organization_id' => $workspace['organization']->id,
            'warehouse_id' => $workspace['warehouse']->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'reserved_quantity' => 0,
        ]);
        $variantStock = Stock::query()->create([
            'organization_id' => $workspace['organization']->id,
            'warehouse_id' => $workspace['warehouse']->id,
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'quantity' => 1,
            'reserved_quantity' => 0,
        ]);

        $baseLot = $this->createLot($workspace, $product->id, null, 'BASE-LOT');
        $variantLot = $this->createLot($workspace, $product->id, $variant->id, 'BASE-LOT');

        $this->assertSame(0, $baseStock->variant_key);
        $this->assertSame($variant->id, $variantStock->variant_key);
        $this->assertSame(0, $baseLot->variant_key);
        $this->assertSame($variant->id, $variantLot->variant_key);
    }

    public function test_only_one_base_stock_row_can_exist_per_warehouse_and_product(): void
    {
        $workspace = $this->createWorkspace();
        $product = $this->createProduct($workspace['organization']);
        $attributes = [
            'organization_id' => $workspace['organization']->id,
            'warehouse_id' => $workspace['warehouse']->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'reserved_quantity' => 0,
        ];

        Stock::query()->create($attributes);

        $this->expectException(QueryException::class);
        Stock::query()->create($attributes);
    }

    public function test_only_one_base_lot_row_can_exist_per_batch(): void
    {
        $workspace = $this->createWorkspace();
        $product = $this->createProduct($workspace['organization']);
        $this->createLot($workspace, $product->id, null, 'UNIQUE-BATCH');

        $this->expectException(QueryException::class);
        $this->createLot($workspace, $product->id, null, 'UNIQUE-BATCH');
    }

    private function createLot(array $workspace, int $productId, ?int $variantId, string $batch): InventoryLot
    {
        return InventoryLot::query()->create([
            'organization_id' => $workspace['organization']->id,
            'warehouse_id' => $workspace['warehouse']->id,
            'product_id' => $productId,
            'product_variant_id' => $variantId,
            'batch_number' => $batch,
            'expiry_date' => today()->addYear(),
            'quantity_received' => 1,
            'quantity_available' => 1,
            'purchase_price' => 1,
            'sale_price' => 2,
            'status' => 'active',
        ]);
    }
}
