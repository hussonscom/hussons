<?php

namespace Tests\Feature;

use App\Models\InventoryUnit;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Stock;
use App\Services\InventoryService;
use App\Services\SaleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Feature\Concerns\CreatesWorkspace;
use Tests\TestCase;

class ProductVariantsTest extends TestCase
{
    use CreatesWorkspace;
    use RefreshDatabase;

    public function test_edit_form_is_populated_with_the_saved_product(): void
    {
        $workspace = $this->createWorkspace();

        $this->actingAs($workspace['user'])
            ->withSession($this->workspaceSession($workspace))
            ->post(route('catalog.store'), $this->productPayload())
            ->assertRedirect();

        $product = Product::query()->where('sku', 'IPHONE-15')->firstOrFail();

        $this->actingAs($workspace['user'])
            ->withSession($this->workspaceSession($workspace))
            ->get(route('catalog.edit', $product))
            ->assertOk()
            ->assertSee('name="name_az" value="Apple iPhone 15"', false)
            ->assertSee('name="sku" value="IPHONE-15"', false)
            ->assertSee('name="variants[0][sku]" value="IPHONE-15-128-BLK"', false);
    }

    public function test_variants_keep_separate_stock_serials_prices_barcodes_and_warranty(): void
    {
        $workspace = $this->createWorkspace();

        $this->actingAs($workspace['user'])
            ->withSession($this->workspaceSession($workspace))
            ->post(route('catalog.store'), $this->productPayload())
            ->assertRedirect();

        $product = Product::query()->where('sku', 'IPHONE-15')->firstOrFail();
        $black = ProductVariant::query()->where('sku', 'IPHONE-15-128-BLK')->firstOrFail();
        $blue = ProductVariant::query()->where('sku', 'IPHONE-15-256-BLU')->firstOrFail();

        $this->assertSame('Black', $black->color);
        $this->assertSame('128 GB', $black->memory_capacity);
        $this->assertSame(24, $black->warranty_months);
        $this->assertSame('2000000000002', $blue->barcode);

        app(InventoryService::class)->receive(
            $workspace['organization']->id,
            $workspace['branch']->id,
            $workspace['warehouse'],
            $workspace['user'],
            ['items' => [[
                'product_id' => $product->id,
                'product_variant_id' => $black->id,
                'quantity' => 1,
                'unit_cost' => 900,
                'units' => [[
                    'serial_number' => 'SER-IP15-BLK-1',
                    'imei1' => '111111111111111',
                ]],
            ]]],
        );

        $unit = InventoryUnit::query()->where('serial_number', 'SER-IP15-BLK-1')->firstOrFail();
        $this->assertSame($black->id, $unit->product_variant_id);
        $this->assertSame(24, $unit->warranty_months);
        $this->assertDatabaseHas('stocks', [
            'product_id' => $product->id,
            'product_variant_id' => $black->id,
            'quantity' => 1,
        ]);

        $sale = app(SaleService::class)->checkout(
            $workspace['organization']->id,
            $workspace['branch'],
            $workspace['warehouse'],
            $workspace['user'],
            [
                'items' => [[
                    'product_id' => $product->id,
                    'product_variant_id' => $black->id,
                    'inventory_unit_ids' => [$unit->id],
                    'quantity' => 1,
                    'unit_price' => 1200,
                ]],
                'payments' => [['method' => 'card', 'amount' => 1200]],
            ],
            'owner',
        );

        $this->assertSame($black->id, $sale->items->first()->product_variant_id);
        $this->assertSame(24, $sale->items->first()->warranty_months);
        $this->assertSame('0.000', Stock::query()->where('product_variant_id', $black->id)->value('quantity'));

        app(InventoryService::class)->receive(
            $workspace['organization']->id,
            $workspace['branch']->id,
            $workspace['warehouse'],
            $workspace['user'],
            ['items' => [[
                'product_id' => $product->id,
                'product_variant_id' => $blue->id,
                'quantity' => 1,
                'unit_cost' => 1000,
                'warranty_months' => 6,
                'units' => [[
                    'serial_number' => 'SER-IP15-BLU-1',
                    'imei1' => '222222222222222',
                ]],
            ]]],
        );

        $this->assertSame(
            6,
            InventoryUnit::query()->where('serial_number', 'SER-IP15-BLU-1')->value('warranty_months'),
        );

        $this->actingAs($workspace['user'])
            ->withSession($this->workspaceSession($workspace))
            ->get(route('sales.create'))
            ->assertOk()
            ->assertSee('2000000000002')
            ->assertSee('256 GB');
    }

    public function test_variant_with_available_stock_cannot_be_removed(): void
    {
        $workspace = $this->createWorkspace();
        $product = $this->createProduct($workspace['organization'], [
            'type' => 'electronic',
            'tracking_mode' => 'none',
            'sku' => 'PHONE-BASE',
        ]);
        $variant = ProductVariant::query()->create([
            'organization_id' => $workspace['organization']->id,
            'product_id' => $product->id,
            'sku' => 'PHONE-BLACK',
            'barcode' => '3000000000001',
            'color' => 'Black',
            'attributes' => [],
            'purchase_price' => 50,
            'sale_price' => 80,
            'warranty_months' => 12,
            'is_active' => true,
        ]);
        Stock::query()->create([
            'organization_id' => $workspace['organization']->id,
            'warehouse_id' => $workspace['warehouse']->id,
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'quantity' => 1,
            'reserved_quantity' => 0,
        ]);

        $updatePayload = array_merge($this->productPayload(), [
            'tracking_mode' => $product->tracking_mode,
            'sku' => $product->sku,
            'name_az' => $product->name_az,
        ]);

        $this->actingAs($workspace['user'])
            ->withSession($this->workspaceSession($workspace))
            ->put(route('catalog.update', $product), array_merge($updatePayload, [
                'variants' => [[
                    'id' => $variant->id,
                    'sku' => $variant->sku,
                    'remove' => 1,
                ]],
            ]))
            ->assertSessionHasErrors('variants.0.remove');

        $this->assertTrue($variant->fresh()->is_active);

        $this->actingAs($workspace['user'])
            ->withSession($this->workspaceSession($workspace))
            ->put(route('catalog.update', $product), array_merge($updatePayload, [
                'variants' => [[
                    'id' => $variant->id,
                    'sku' => $variant->sku,
                    'active' => 0,
                ]],
            ]))
            ->assertSessionHasErrors('variants.0.active');

        $this->assertTrue($variant->fresh()->is_active);
    }

    public function test_tracking_mode_cannot_change_after_inventory_activity(): void
    {
        $workspace = $this->createWorkspace();
        $product = $this->createProduct($workspace['organization']);
        Stock::query()->create([
            'organization_id' => $workspace['organization']->id,
            'warehouse_id' => $workspace['warehouse']->id,
            'product_id' => $product->id,
            'quantity' => 0,
            'reserved_quantity' => 0,
        ]);

        $this->actingAs($workspace['user'])
            ->withSession($this->workspaceSession($workspace))
            ->put(route('catalog.update', $product), [
                'type' => $product->type,
                'tracking_mode' => 'serial',
                'sku' => $product->sku,
                'name_az' => $product->name_az,
                'unit' => $product->unit,
                'purchase_price' => $product->purchase_price,
                'sale_price' => $product->sale_price,
                'tax_rate' => $product->tax_rate,
                'min_stock' => $product->min_stock,
                'active' => 1,
            ])
            ->assertSessionHasErrors('tracking_mode');

        $this->assertSame('none', $product->fresh()->tracking_mode);
    }

    public function test_inventory_and_sale_requests_reject_exponent_numbers_and_future_prescriptions(): void
    {
        $workspace = $this->createWorkspace();
        $product = $this->createProduct($workspace['organization']);

        $this->actingAs($workspace['user'])
            ->withSession($this->workspaceSession($workspace))
            ->post(route('inventory.store'), [
                'idempotency_key' => (string) Str::uuid(),
                'warehouse_id' => $workspace['warehouse']->id,
                'items' => [[
                    'product_id' => $product->id,
                    'quantity' => '1e2',
                    'unit_cost' => '5e1',
                ]],
            ])
            ->assertSessionHasErrors(['items.0.quantity', 'items.0.unit_cost']);

        $this->actingAs($workspace['user'])
            ->withSession($this->workspaceSession($workspace))
            ->post(route('sales.store'), [
                'idempotency_key' => (string) Str::uuid(),
                'branch_id' => $workspace['branch']->id,
                'warehouse_id' => $workspace['warehouse']->id,
                'items' => [[
                    'product_id' => $product->id,
                    'quantity' => '1e0',
                    'unit_price' => '8e1',
                ]],
                'prescription' => ['issued_at' => today()->addDay()->toDateString()],
                'payments' => [['method' => 'cash', 'amount' => '8e1']],
            ])
            ->assertSessionHasErrors([
                'items.0.quantity',
                'items.0.unit_price',
                'payments.0.amount',
                'prescription.issued_at',
            ]);
    }

    private function productPayload(): array
    {
        return [
            'type' => 'electronic',
            'tracking_mode' => 'serial',
            'sku' => 'IPHONE-15',
            'barcode' => null,
            'name_az' => 'Apple iPhone 15',
            'name_ru' => 'Apple iPhone 15',
            'name_en' => 'Apple iPhone 15',
            'unit' => 'pcs',
            'purchase_price' => 850,
            'sale_price' => 1100,
            'tax_rate' => 0,
            'min_stock' => 0,
            'active' => 1,
            'variants' => [
                [
                    'sku' => 'IPHONE-15-128-BLK',
                    'barcode' => '2000000000001',
                    'model' => 'iPhone 15',
                    'color' => 'Black',
                    'memory_capacity' => '128 GB',
                    'purchase_price' => 900,
                    'sale_price' => 1200,
                    'warranty_months' => 24,
                    'active' => 1,
                ],
                [
                    'sku' => 'IPHONE-15-256-BLU',
                    'barcode' => '2000000000002',
                    'model' => 'iPhone 15',
                    'color' => 'Blue',
                    'memory_capacity' => '256 GB',
                    'purchase_price' => 1000,
                    'sale_price' => 1300,
                    'warranty_months' => 24,
                    'active' => 1,
                ],
            ],
        ];
    }
}
