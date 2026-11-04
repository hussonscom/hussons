<?php

namespace Tests\Feature;

use App\Models\InventoryMovement;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\Stock;
use App\Services\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Feature\Concerns\CreatesWorkspace;
use Tests\TestCase;

class OperationIdempotencyTest extends TestCase
{
    use CreatesWorkspace;
    use RefreshDatabase;

    public function test_repeating_the_same_sale_post_only_completes_the_sale_once(): void
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
                'quantity' => 10,
                'unit_cost' => 50,
            ]]],
        );

        $payload = [
            'idempotency_key' => (string) Str::uuid(),
            'branch_id' => $workspace['branch']->id,
            'warehouse_id' => $workspace['warehouse']->id,
            'items' => [[
                'product_id' => $product->id,
                'quantity' => 2,
                'unit_price' => 80,
            ]],
            'payments' => [['method' => 'card', 'amount' => 160]],
        ];
        $client = $this->actingAs($workspace['user'])
            ->withSession($this->workspaceSession($workspace));

        $client->post(route('sales.store'), $payload)->assertRedirect();
        $sale = Sale::query()->firstOrFail();
        $client->post(route('sales.store'), $payload)
            ->assertRedirect(route('sales.show', $sale));

        $this->assertSame(1, Sale::query()->count());
        $this->assertSame(1, $sale->payments()->count());
        $this->assertSame(1, $sale->fiscalReceipts()->where('operation', 'issue')->count());
        $this->assertSame(1, InventoryMovement::query()->where('type', 'sale')->count());
        $this->assertSame('8.000', Stock::query()->where('product_id', $product->id)->value('quantity'));

        $changedPayload = $payload;
        $changedPayload['items'][0]['quantity'] = 3;
        $changedPayload['payments'][0]['amount'] = 240;

        $client->post(route('sales.store'), $changedPayload)
            ->assertSessionHasErrors('idempotency_key');

        $this->assertSame(1, Sale::query()->count());
        $this->assertSame(1, $sale->payments()->count());
        $this->assertSame(1, $sale->fiscalReceipts()->where('operation', 'issue')->count());
        $this->assertSame(1, InventoryMovement::query()->where('type', 'sale')->count());
        $this->assertSame('8.000', Stock::query()->where('product_id', $product->id)->value('quantity'));
    }

    public function test_repeating_the_same_receive_post_only_receives_stock_once(): void
    {
        $workspace = $this->createWorkspace();
        $product = $this->createProduct($workspace['organization']);
        $payload = [
            'idempotency_key' => (string) Str::uuid(),
            'warehouse_id' => $workspace['warehouse']->id,
            'items' => [[
                'product_id' => $product->id,
                'quantity' => 4,
                'unit_cost' => 50,
            ]],
        ];
        $client = $this->actingAs($workspace['user'])
            ->withSession($this->workspaceSession($workspace));

        $client->post(route('inventory.store'), $payload)->assertRedirect();
        $purchase = Purchase::query()->firstOrFail();
        $client->post(route('inventory.store'), $payload)
            ->assertRedirect(route('inventory.purchase', $purchase));

        $this->assertSame(1, Purchase::query()->count());
        $this->assertSame(1, $purchase->items()->count());
        $this->assertSame(1, InventoryMovement::query()->where('type', 'purchase')->count());
        $this->assertSame('4.000', Stock::query()->where('product_id', $product->id)->value('quantity'));

        $changedPayload = $payload;
        $changedPayload['items'][0]['quantity'] = 5;

        $client->post(route('inventory.store'), $changedPayload)
            ->assertSessionHasErrors('idempotency_key');

        $this->assertSame(1, Purchase::query()->count());
        $this->assertSame(1, $purchase->items()->count());
        $this->assertSame(1, InventoryMovement::query()->where('type', 'purchase')->count());
        $this->assertSame('4.000', Stock::query()->where('product_id', $product->id)->value('quantity'));
    }
}
