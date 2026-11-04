<?php

namespace Tests\Feature;

use App\Models\InventoryUnit;
use App\Models\Stock;
use App\Models\TeamInvitation;
use App\Models\User;
use App\Services\InventoryService;
use App\Services\SaleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Feature\Concerns\CreatesWorkspace;
use Tests\TestCase;

final class InvitationAndReturnInspectionTest extends TestCase
{
    use CreatesWorkspace;
    use RefreshDatabase;

    public function test_owner_can_issue_and_new_employee_can_accept_a_one_time_invitation(): void
    {
        $workspace = $this->createWorkspace();
        $email = 'employee-'.Str::lower(Str::random(8)).'@example.test';

        $response = $this->actingAs($workspace['user'])
            ->withSession($this->workspaceSession($workspace))
            ->post(route('team.invitations.store'), [
                'email' => $email,
                'role' => 'cashier',
                'branch_id' => $workspace['branch']->id,
            ]);

        $response->assertRedirect()->assertSessionHas('invitation_url');
        $url = $response->getSession()->get('invitation_url');
        $token = basename(parse_url($url, PHP_URL_PATH));
        $invitation = TeamInvitation::query()->firstOrFail();

        $this->assertSame(hash('sha256', $token), $invitation->token_hash);
        $this->assertStringNotContainsString($token, json_encode($invitation->getAttributes(), JSON_THROW_ON_ERROR));

        auth()->logout();
        $this->get($url)->assertOk()->assertSee($workspace['organization']->name);
        $this->post($url, [
            'name' => 'New Cashier',
            'password' => 'StrongPass123',
            'password_confirmation' => 'StrongPass123',
            'locale' => 'en',
        ])->assertRedirect(route('dashboard'));

        $user = User::query()->where('email', $email)->firstOrFail();
        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseHas('memberships', [
            'organization_id' => $workspace['organization']->id,
            'user_id' => $user->id,
            'branch_id' => $workspace['branch']->id,
            'role' => 'cashier',
            'is_active' => true,
        ]);
        $this->assertNotNull($invitation->fresh()->accepted_at);
        auth()->logout();
        $this->get($url)->assertStatus(410);
    }

    public function test_serial_return_is_quarantined_until_warehouse_inspection(): void
    {
        $workspace = $this->createWorkspace();
        $product = $this->createProduct($workspace['organization'], [
            'type' => 'electronic',
            'tracking_mode' => 'serial',
            'sale_price' => 900,
        ]);

        app(InventoryService::class)->receive(
            $workspace['organization']->id,
            $workspace['branch']->id,
            $workspace['warehouse'],
            $workspace['user'],
            ['items' => [[
                'product_id' => $product->id,
                'quantity' => 1,
                'unit_cost' => 700,
                'units' => [['serial_number' => 'RETURN-UNIT-01', 'imei1' => '351111111111119']],
            ]]],
        );
        $unit = InventoryUnit::query()->firstOrFail();

        $sale = app(SaleService::class)->checkout(
            $workspace['organization']->id,
            $workspace['branch'],
            $workspace['warehouse'],
            $workspace['user'],
            [
                'items' => [[
                    'product_id' => $product->id,
                    'quantity' => 1,
                    'unit_price' => 900,
                    'inventory_unit_ids' => [$unit->id],
                ]],
                'payments' => [['method' => 'card', 'amount' => 900]],
            ],
            'owner',
        );

        app(SaleService::class)->refund($workspace['organization']->id, $sale, $workspace['user'], 'Unopened return');

        $this->assertSame('returned', $unit->fresh()->status);
        $this->assertSame('0.000', Stock::query()->where('product_id', $product->id)->value('quantity'));

        $this->actingAs($workspace['user'])
            ->withSession($this->workspaceSession($workspace))
            ->patch(route('inventory.returns.update', $unit), [
                'disposition' => 'in_stock',
                'notes' => 'Seal and IMEI checked',
            ])
            ->assertRedirect();

        $this->assertSame('in_stock', $unit->fresh()->status);
        $this->assertSame('1.000', Stock::query()->where('product_id', $product->id)->value('quantity'));
        $this->assertDatabaseHas('inventory_movements', [
            'inventory_unit_id' => $unit->id,
            'type' => 'return_in_stock',
            'quantity' => 1,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'organization_id' => $workspace['organization']->id,
            'action' => 'inventory.return_inspected',
            'auditable_id' => $unit->id,
        ]);

        $this->actingAs($workspace['user'])
            ->withSession($this->workspaceSession($workspace))
            ->patch(route('inventory.returns.update', $unit), ['disposition' => 'writeoff'])
            ->assertStatus(409);
    }
}
