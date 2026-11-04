<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\CreatesWorkspace;
use Tests\TestCase;

class TenantIsolationTest extends TestCase
{
    use CreatesWorkspace;
    use RefreshDatabase;

    public function test_a_member_cannot_edit_another_organizations_product(): void
    {
        $first = $this->createWorkspace();
        $second = $this->createWorkspace();
        $foreignProduct = $this->createProduct($second['organization']);

        $this->actingAs($first['user'])
            ->withSession($this->workspaceSession($first))
            ->get(route('catalog.edit', $foreignProduct))
            ->assertNotFound();
    }

    public function test_a_member_cannot_switch_to_an_unassigned_organization(): void
    {
        $first = $this->createWorkspace();
        $second = $this->createWorkspace();

        $this->actingAs($first['user'])
            ->withSession($this->workspaceSession($first))
            ->patch(route('organization.context'), [
                'organization_id' => $second['organization']->id,
                'branch_id' => $second['branch']->id,
            ])
            ->assertNotFound();
    }
}
