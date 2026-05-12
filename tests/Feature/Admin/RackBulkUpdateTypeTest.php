<?php

namespace Tests\Feature\Admin;

use App\Services\FirebaseService;
use Mockery;
use Tests\TestCase;

class RackBulkUpdateTypeTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_admin_can_bulk_update_selected_rack_types(): void
    {
        $firebase = Mockery::mock(FirebaseService::class);
        $firebase->shouldReceive('getRacks')->once()->andReturn([
            [
                'id' => 'rack-1',
                'name' => 'Rak A',
                'location' => 'Gudang 1',
                'description' => 'Desc A',
                'is_active' => true,
                'rack_type' => 'storage',
                'check_order' => 1,
            ],
            [
                'id' => 'rack-2',
                'name' => 'Rak B',
                'location' => 'Gudang 2',
                'description' => 'Desc B',
                'is_active' => false,
                'rack_type' => 'storage',
                'check_order' => 2,
            ],
            [
                'id' => 'rack-3',
                'name' => 'Rak C',
                'location' => 'Gudang 3',
                'description' => 'Desc C',
                'is_active' => true,
                'rack_type' => 'display',
                'check_order' => 3,
            ],
        ]);

        $firebase->shouldReceive('updateRack')->once()->with('rack-1', [
            'name' => 'Rak A',
            'location' => 'Gudang 1',
            'description' => 'Desc A',
            'is_active' => true,
            'rack_type' => 'display',
            'check_order' => 1,
        ]);
        $firebase->shouldReceive('updateRack')->once()->with('rack-2', [
            'name' => 'Rak B',
            'location' => 'Gudang 2',
            'description' => 'Desc B',
            'is_active' => false,
            'rack_type' => 'display',
            'check_order' => 2,
        ]);
        $firebase->shouldReceive('logAuditAction')->once()->with('bulk_update_type', 'rack', null, Mockery::on(function ($details) {
            return ($details['rack_type'] ?? null) === 'display'
                && ($details['count'] ?? null) === 2
                && ($details['rack_ids'] ?? []) === ['rack-1', 'rack-2'];
        }));

        $this->instance(FirebaseService::class, $firebase);

        $response = $this->withSession([
            'admin_authenticated' => true,
            'admin_id' => 'admin-test',
        ])->post(route('admin.racks.bulk_update_type'), [
            'rack_ids' => ['rack-1', 'rack-2'],
            'rack_type' => 'display',
        ]);

        $response->assertRedirect(route('admin.racks.index'));
        $response->assertSessionHas('success', '2 rak berhasil diubah ke tipe Display.');
    }

    public function test_bulk_update_requires_selected_racks(): void
    {
        $firebase = Mockery::mock(FirebaseService::class);
        $firebase->shouldNotReceive('getRacks');
        $firebase->shouldNotReceive('updateRack');
        $firebase->shouldNotReceive('logAuditAction');

        $this->instance(FirebaseService::class, $firebase);

        $response = $this->from(route('admin.racks.index'))
            ->withSession([
                'admin_authenticated' => true,
                'admin_id' => 'admin-test',
            ])->post(route('admin.racks.bulk_update_type'), [
                'rack_type' => 'storage',
            ]);

        $response->assertRedirect(route('admin.racks.index'));
        $response->assertSessionHasErrors(['rack_ids']);
    }

    public function test_bulk_update_requires_valid_rack_type(): void
    {
        $firebase = Mockery::mock(FirebaseService::class);
        $firebase->shouldNotReceive('getRacks');
        $firebase->shouldNotReceive('updateRack');
        $firebase->shouldNotReceive('logAuditAction');

        $this->instance(FirebaseService::class, $firebase);

        $response = $this->from(route('admin.racks.index'))
            ->withSession([
                'admin_authenticated' => true,
                'admin_id' => 'admin-test',
            ])->post(route('admin.racks.bulk_update_type'), [
                'rack_ids' => ['rack-1'],
                'rack_type' => 'invalid',
            ]);

        $response->assertRedirect(route('admin.racks.index'));
        $response->assertSessionHasErrors(['rack_type']);
    }
}
