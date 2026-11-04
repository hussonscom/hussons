<?php

namespace Tests\Feature;

use App\Models\Membership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\CreatesWorkspace;
use Tests\TestCase;

class TeamAndPlatformAccessTest extends TestCase
{
    use CreatesWorkspace;
    use RefreshDatabase;

    public function test_owner_can_add_an_existing_active_user_to_the_team(): void
    {
        $workspace = $this->createWorkspace();
        $newMember = User::factory()->create([
            'email' => 'cashier@example.test',
            'is_active' => true,
        ]);

        $this->actingAs($workspace['user'])
            ->withSession($this->workspaceSession($workspace))
            ->post(route('team.store'), [
                'email' => strtoupper($newMember->email),
                'role' => 'cashier',
                'branch_id' => $workspace['branch']->id,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('memberships', [
            'organization_id' => $workspace['organization']->id,
            'user_id' => $newMember->id,
            'branch_id' => $workspace['branch']->id,
            'role' => 'cashier',
            'is_active' => true,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'organization_id' => $workspace['organization']->id,
            'actor_id' => $workspace['user']->id,
            'action' => 'team.member_added',
        ]);
    }

    public function test_admin_cannot_modify_an_owner_and_the_last_owner_cannot_be_demoted(): void
    {
        $workspace = $this->createWorkspace();
        $admin = User::factory()->create(['is_active' => true]);
        Membership::query()->create([
            'organization_id' => $workspace['organization']->id,
            'user_id' => $admin->id,
            'branch_id' => null,
            'role' => 'admin',
            'permissions' => null,
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->withSession($this->workspaceSession($workspace))
            ->patch(route('team.update', $workspace['membership']), [
                'role' => 'admin',
                'branch_id' => null,
                'is_active' => '1',
            ])
            ->assertForbidden();

        $this->actingAs($workspace['user'])
            ->withSession($this->workspaceSession($workspace))
            ->from(route('team.index'))
            ->patch(route('team.update', $workspace['membership']), [
                'role' => 'admin',
                'branch_id' => null,
                'is_active' => '1',
            ])
            ->assertRedirect(route('team.index'))
            ->assertSessionHasErrors('role');

        $workspace['membership']->refresh();
        $this->assertSame('owner', $workspace['membership']->role);
        $this->assertTrue($workspace['membership']->is_active);
    }

    public function test_platform_console_requires_the_platform_admin_flag(): void
    {
        $this->withoutVite();
        $regular = User::factory()->create(['is_platform_admin' => false]);
        $platformAdmin = User::factory()->create(['is_platform_admin' => true]);

        $this->actingAs($regular)
            ->get(route('platform.index'))
            ->assertForbidden();

        $this->actingAs($platformAdmin)
            ->get(route('platform.index'))
            ->assertOk()
            ->assertSee('Hussons One');
    }
}
