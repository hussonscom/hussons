<?php

namespace Tests\Feature;

use App\Models\RestaurantOrder;
use App\Models\RestaurantTable;
use App\Models\Sale;
use App\Models\Stock;
use App\Services\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\CreatesWorkspace;
use Tests\TestCase;

class RestaurantWorkflowTest extends TestCase
{
    use CreatesWorkspace;
    use RefreshDatabase;

    public function test_order_moves_through_kitchen_states_and_releases_the_table(): void
    {
        $workspace = $this->createWorkspace();
        $food = $this->createProduct($workspace['organization'], [
            'type' => 'food',
            'sale_price' => 12.50,
        ]);
        $table = RestaurantTable::query()->create([
            'organization_id' => $workspace['organization']->id,
            'branch_id' => $workspace['branch']->id,
            'code' => 'T-01',
            'name' => 'Table 1',
            'seats' => 4,
            'status' => 'available',
            'is_active' => true,
        ]);

        app(InventoryService::class)->receive(
            $workspace['organization']->id,
            $workspace['branch']->id,
            $workspace['warehouse'],
            $workspace['user'],
            ['items' => [[
                'product_id' => $food->id,
                'quantity' => 10,
                'unit_cost' => 5,
            ]]],
        );

        $client = $this->actingAs($workspace['user'])
            ->withSession($this->workspaceSession($workspace));

        $client->post(route('restaurant.orders.store'), [
            'table_id' => $table->id,
            'guest_count' => 2,
            'items' => [[
                'product_id' => $food->id,
                'quantity' => 2,
            ]],
        ])->assertRedirect();

        $order = RestaurantOrder::query()->firstOrFail();
        $this->assertSame('new', $order->status);
        $this->assertSame('25.00', $order->total);
        $this->assertSame('occupied', $table->fresh()->status);

        foreach (['preparing', 'ready', 'served'] as $status) {
            $client->patch(route('restaurant.orders.status', $order), ['status' => $status])
                ->assertRedirect();
            $this->assertSame($status, $order->fresh()->status);
        }

        $client->patchJson(route('restaurant.orders.status', $order), ['status' => 'paid'])
            ->assertUnprocessable();

        $client->post(route('restaurant.orders.pay', $order), [
            'warehouse_id' => $workspace['warehouse']->id,
            'payment_method' => 'cash',
            'amount_received' => 25,
        ])->assertRedirect();

        $order->refresh();
        $sale = Sale::query()->findOrFail($order->sale_id);

        $this->assertSame('paid', $order->status);
        $this->assertNotNull($order->closed_at);
        $this->assertSame('available', $table->fresh()->status);
        $this->assertSame('completed', $sale->status);
        $this->assertSame('25.00', $sale->total);
        $this->assertDatabaseHas('payments', [
            'sale_id' => $sale->id,
            'method' => 'cash',
            'amount' => 25,
        ]);
        $this->assertDatabaseHas('fiscal_receipts', [
            'sale_id' => $sale->id,
            'provider' => 'internal',
            'status' => 'internal',
        ]);
        $this->assertSame('8.000', Stock::query()->where('product_id', $food->id)->value('quantity'));

        $client->post(route('restaurant.orders.pay', $order), [
            'warehouse_id' => $workspace['warehouse']->id,
            'payment_method' => 'cash',
            'amount_received' => 25,
        ])->assertRedirect(route('sales.show', $sale));

        $this->assertSame(1, Sale::query()->count());
        $this->assertSame(1, $sale->payments()->count());
        $this->assertSame(1, $sale->fiscalReceipts()->where('operation', 'issue')->count());
        $this->assertSame('8.000', Stock::query()->where('product_id', $food->id)->value('quantity'));

        $client->post(route('restaurant.orders.pay', $order), [
            'warehouse_id' => $workspace['warehouse']->id,
            'payment_method' => 'cash',
            'amount_received' => 30,
        ])->assertSessionHasErrors('idempotency_key');

        $this->assertSame(1, Sale::query()->count());
        $this->assertSame('8.000', Stock::query()->where('product_id', $food->id)->value('quantity'));
    }

    public function test_restaurant_screen_only_renders_actions_allowed_for_the_current_role(): void
    {
        $workspace = $this->createWorkspace(role: 'kitchen');
        $this->createProduct($workspace['organization'], [
            'type' => 'food',
            'sale_price' => 8,
        ]);

        $orders = collect(['new', 'preparing', 'ready', 'served'])->mapWithKeys(
            function (string $status) use ($workspace): array {
                $order = RestaurantOrder::query()->create([
                    'organization_id' => $workspace['organization']->id,
                    'branch_id' => $workspace['branch']->id,
                    'user_id' => $workspace['user']->id,
                    'number' => 'ROLE-'.strtoupper($status),
                    'status' => $status,
                    'guest_count' => 1,
                    'subtotal' => 8,
                    'discount' => 0,
                    'tax' => 0,
                    'total' => 8,
                    'opened_at' => now(),
                ]);

                return [$status => $order];
            },
        );

        $client = $this->actingAs($workspace['user'])
            ->withSession($this->workspaceSession($workspace));

        $client->get(route('restaurant.index'))
            ->assertOk()
            ->assertDontSee('data-dialog-open="table-dialog"', false)
            ->assertDontSee('data-dialog-open="order-dialog"', false)
            ->assertSee('name="status" value="preparing"', false)
            ->assertSee('name="status" value="ready"', false)
            ->assertDontSee('name="status" value="cancelled"', false)
            ->assertDontSee('name="status" value="served"', false)
            ->assertDontSee(route('restaurant.orders.pay', $orders['served']), false);

        $workspace['membership']->update(['role' => 'cashier']);

        $client->get(route('restaurant.index'))
            ->assertOk()
            ->assertDontSee('data-dialog-open="table-dialog"', false)
            ->assertSee('data-dialog-open="order-dialog"', false)
            ->assertDontSee('name="status" value="preparing"', false)
            ->assertDontSee('name="status" value="ready"', false)
            ->assertSee('name="status" value="cancelled"', false)
            ->assertSee('name="status" value="served"', false)
            ->assertSee(route('restaurant.orders.pay', $orders['served']), false);
    }
}
