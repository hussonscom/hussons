<?php

namespace Tests\Feature;

use App\Models\InventoryUnit;
use App\Models\Product;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\CreatesWorkspace;
use Tests\TestCase;

class InventoryIdentifierUniquenessTest extends TestCase
{
    use CreatesWorkspace;
    use RefreshDatabase;

    public function test_identifiers_are_synchronized_when_a_unit_is_created_or_updated(): void
    {
        $workspace = $this->createWorkspace();
        $product = $this->serialProduct($workspace['organization']);
        $unit = $this->createUnit($workspace, $product, 'SERIAL-A', '111111111111111', '222222222222222');

        $this->assertDatabaseHas('inventory_unit_identifiers', [
            'inventory_unit_id' => $unit->id,
            'kind' => 'serial',
            'value' => 'SERIAL-A',
        ]);
        $this->assertDatabaseHas('inventory_unit_identifiers', [
            'inventory_unit_id' => $unit->id,
            'kind' => 'imei1',
            'value' => '111111111111111',
        ]);
        $this->assertDatabaseHas('inventory_unit_identifiers', [
            'inventory_unit_id' => $unit->id,
            'kind' => 'imei2',
            'value' => '222222222222222',
        ]);

        $unit->update([
            'imei1' => '333333333333333',
            'imei2' => null,
        ]);

        $this->assertDatabaseMissing('inventory_unit_identifiers', ['value' => '111111111111111']);
        $this->assertDatabaseMissing('inventory_unit_identifiers', ['value' => '222222222222222']);
        $this->assertDatabaseHas('inventory_unit_identifiers', [
            'inventory_unit_id' => $unit->id,
            'kind' => 'imei1',
            'value' => '333333333333333',
        ]);
    }

    public function test_an_imei_cannot_be_reused_in_another_imei_column(): void
    {
        $workspace = $this->createWorkspace();
        $product = $this->serialProduct($workspace['organization']);
        $this->createUnit($workspace, $product, 'SERIAL-A', '111111111111111', null);

        try {
            $this->createUnit($workspace, $product, 'SERIAL-B', null, '111111111111111');
            $this->fail('A cross-column duplicate IMEI was accepted.');
        } catch (QueryException) {
            $this->assertDatabaseMissing('inventory_units', ['serial_number' => 'SERIAL-B']);
            $this->assertDatabaseCount('inventory_unit_identifiers', 2);
        }
    }

    public function test_a_conflicting_identifier_update_rolls_back_the_unit_and_identifier_rows(): void
    {
        $workspace = $this->createWorkspace();
        $product = $this->serialProduct($workspace['organization']);
        $first = $this->createUnit($workspace, $product, 'SERIAL-A', '111111111111111', null);
        $second = $this->createUnit($workspace, $product, 'SERIAL-B', null, '222222222222222');

        try {
            $second->update(['imei2' => $first->imei1]);
            $this->fail('A conflicting identifier update was accepted.');
        } catch (QueryException) {
            $this->assertSame('222222222222222', $second->fresh()->imei2);
            $this->assertDatabaseHas('inventory_unit_identifiers', [
                'inventory_unit_id' => $second->id,
                'kind' => 'imei2',
                'value' => '222222222222222',
            ]);
            $this->assertDatabaseCount('inventory_unit_identifiers', 4);
        }
    }

    private function serialProduct($organization): Product
    {
        return $this->createProduct($organization, [
            'type' => 'electronic',
            'tracking_mode' => 'serial',
        ]);
    }

    private function createUnit(
        array $workspace,
        Product $product,
        string $serial,
        ?string $imei1,
        ?string $imei2,
    ): InventoryUnit {
        return InventoryUnit::query()->create([
            'organization_id' => $workspace['organization']->id,
            'warehouse_id' => $workspace['warehouse']->id,
            'product_id' => $product->id,
            'serial_number' => $serial,
            'imei1' => $imei1,
            'imei2' => $imei2,
            'purchase_price' => 100,
            'warranty_months' => 12,
            'status' => 'in_stock',
            'received_at' => now(),
            'metadata' => [],
        ]);
    }
}
