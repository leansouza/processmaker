<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use ProcessMaker\Models\User;
use ProcessMaker\Models\Group;
use Tests\Feature\Shared\RequestHelper;

class GroupTest extends TestCase
{
    use RequestHelper;
    use DatabaseTransactions;
     /**
     * Test to make sure the controller and route work with the view
     *
     * @return void
     */
    public function testIndexRoute()
    {
      $response = $this->webCall('GET', '/admin/groups');
      $response->assertStatus(200);
      $response->assertViewIs('admin.groups.index');
    }
     /**
     * Test to make sure the controller and route work with the view
     *
     * @return void
     */
    public function testEditRoute()
    {
      $group = factory(Group::class)->create(['name'=>"Test Edit"]);
      $response = $this->webCall('GET', '/admin/groups/'.$group->uuid_text . '/edit');
      $response->assertStatus(200);
      $response->assertViewIs('admin.groups.edit');
      $response->assertSee('Test Edit');
    }

    public function testShowRoute()
    {
        $group_uuid = factory(Group::class)->create(['name'=>'Test show'])->uuid_text;
        $response = $this->webGet('/admin/groups/'. $group_uuid);
        $response->assertStatus(200);
        $response->assertSee('Test show');
    }
}
