<?php

namespace Tests\Feature;

use App\Models\InventoryLot;
use App\Models\InventoryUnit;
use App\Models\SaleItem;
use App\Models\Stock;
use App\Services\InventoryService;
use App\Services\SaleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Feature\Concerns\CreatesWorkspace;
use Tests\TestCase;

class InventoryAndSalesTest extends TestCase
{
    use CreatesWorkspace;
    use RefreshDatabase;

    public function test_standard_stock_is_received_sold_and_receipted_atomically(): void
    {
        $workspace = $this->createWorkspace();
        $product = $this->createProduct($workspace['organization']);

        app(InventoryService::class)->receive(
            $workspace['organization']->id,
            $workspace['branch']->id,
            $workspace['warehouse'],
            $workspace['user'],
            ['items' => [[
                'product_id' => $product->id,
                'quantity' => 5,
                'unit_cost' => 50,
            ]]],
        );

        $sale = app(SaleService::class)->checkout(
            $workspace['organization']->id,
            $workspace['branch'],
            $workspace['warehouse'],
            $workspace['user'],
            [
                'items' => [[
                    'product_id' => $product->id,
                    'quantity' => 2,
                    'unit_price' => 80,
                ]],
                'payments' => [['method' => 'card', 'amount' => 160]],
            ],
            'owner',
        );

        $stock = Stock::query()->where('product_id', $product->id)->firstOrFail();

        $this->assertSame('completed', $sale->status);
        $this->assertSame('160.00', $sale->total);
        $this->assertSame('3.000', $stock->quantity);
        $this->assertDatabaseHas('sale_items', ['sale_id' => $sale->id, 'product_id' => $product->id]);
        $this->assertDatabaseHas('fiscal_receipts', [
            'sale_id' => $sale->id,
            'provider' => 'internal',
            'status' => 'internal',
        ]);
    }

    public function test_serial_sale_moves_only_the_selected_imei_unit(): void
    {
        $workspace = $this->createWorkspace();
        $product = $this->createProduct($workspace['organization'], [
            'type' => 'electronic',
            'tracking_mode' => 'serial',
            'sale_price' => 1200,
        ]);

        app(InventoryService::class)->receive(
            $workspace['organization']->id,
            $workspace['branch']->id,
            $workspace['warehouse'],
            $workspace['user'],
            ['items' => [[
                'product_id' => $product->id,
                'quantity' => 2,
                'unit_cost' => 900,
                'units' => [
                    ['serial_number' => 'SER-ONE-01', 'imei1' => '111111111111111'],
                    ['serial_number' => 'SER-ONE-02', 'imei1' => '222222222222222'],
                ],
            ]]],
        );

        $selected = InventoryUnit::query()->where('serial_number', 'SER-ONE-01')->firstOrFail();
        $remaining = InventoryUnit::query()->where('serial_number', 'SER-ONE-02')->firstOrFail();

        $sale = app(SaleService::class)->checkout(
            $workspace['organization']->id,
            $workspace['branch'],
            $workspace['warehouse'],
            $workspace['user'],
            [
                'items' => [[
                    'product_id' => $product->id,
                    'quantity' => 1,
                    'unit_price' => 1200,
                    'inventory_unit_ids' => [$selected->id],
                ]],
                'payments' => [['method' => 'cash', 'amount' => 1200]],
            ],
            'owner',
        );

        $this->assertSame('sold', $selected->fresh()->status);
        $this->assertSame('in_stock', $remaining->fresh()->status);
        $this->assertSame($sale->items->first()->id, $selected->fresh()->sale_item_id);
        $this->assertSame('1.000', Stock::query()->where('product_id', $product->id)->value('quantity'));
    }

    public function test_batch_sale_uses_fefo_and_never_allocates_an_expired_lot(): void
    {
        $workspace = $this->createWorkspace();
        $product = $this->createProduct($workspace['organization'], [
            'type' => 'drug',
            'tracking_mode' => 'batch',
            'sale_price' => 10,
        ]);

        $inventory = app(InventoryService::class);
        $inventory->receive(
            $workspace['organization']->id,
            $workspace['branch']->id,
            $workspace['warehouse'],
            $workspace['user'],
            ['items' => [[
                'product_id' => $product->id,
                'quantity' => 2,
                'unit_cost' => 4,
                'batch_number' => 'LATE-LOT',
                'expiry_date' => today()->addDays(90)->toDateString(),
            ]]],
        );
        $inventory->receive(
            $workspace['organization']->id,
            $workspace['branch']->id,
            $workspace['warehouse'],
            $workspace['user'],
            ['items' => [[
                'product_id' => $product->id,
                'quantity' => 2,
                'unit_cost' => 4,
                'batch_number' => 'EARLY-LOT',
                'expiry_date' => today()->addDays(30)->toDateString(),
            ]]],
        );

        $expired = InventoryLot::query()->create([
            'organization_id' => $workspace['organization']->id,
            'warehouse_id' => $workspace['warehouse']->id,
            'product_id' => $product->id,
            'batch_number' => 'EXPIRED-LOT',
            'expiry_date' => today()->subDay(),
            'quantity_received' => 5,
            'quantity_available' => 5,
            'purchase_price' => 3,
            'sale_price' => 10,
            'status' => 'active',
        ]);

        $sale = app(SaleService::class)->checkout(
            $workspace['organization']->id,
            $workspace['branch'],
            $workspace['warehouse'],
            $workspace['user'],
            [
                'items' => [[
                    'product_id' => $product->id,
                    'quantity' => 3,
                    'unit_price' => 10,
                ]],
                'payments' => [['method' => 'cash', 'amount' => 30]],
            ],
            'pharmacist',
        );

        $early = InventoryLot::query()->where('batch_number', 'EARLY-LOT')->firstOrFail();
        $late = InventoryLot::query()->where('batch_number', 'LATE-LOT')->firstOrFail();

        $this->assertSame('0.000', $early->quantity_available);
        $this->assertSame('1.000', $late->quantity_available);
        $this->assertSame('5.000', $expired->fresh()->quantity_available);
        $this->assertCount(2, SaleItem::query()->where('sale_id', $sale->id)->get());

        $this->expectException(ValidationException::class);

        app(SaleService::class)->checkout(
            $workspace['organization']->id,
            $workspace['branch'],
            $workspace['warehouse'],
            $workspace['user'],
            [
                'items' => [[
                    'product_id' => $product->id,
                    'quantity' => 1,
                    'unit_price' => 10,
                    'inventory_lot_id' => $expired->id,
                ]],
                'payments' => [['method' => 'cash', 'amount' => 10]],
            ],
            'pharmacist',
        );
    }
}
