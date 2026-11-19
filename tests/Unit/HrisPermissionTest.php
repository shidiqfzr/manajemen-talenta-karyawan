<?php

namespace Tests\Unit;

use App\Support\HrisPermission;
use Tests\TestCase;

class HrisPermissionTest extends TestCase
{
    public function test_admin_holds_every_mutation_permission_by_default(): void
    {
        $this->assertTrue(HrisPermission::roleHas('admin', HrisPermission::MUTASI_PREPARE));
        $this->assertTrue(HrisPermission::roleHas('admin', HrisPermission::MUTASI_APPROVE));
    }

    public function test_unknown_or_missing_roles_hold_nothing(): void
    {
        $this->assertFalse(HrisPermission::roleHas('user', HrisPermission::MUTASI_PREPARE));
        $this->assertFalse(HrisPermission::roleHas(null, HrisPermission::MUTASI_APPROVE));
        $this->assertFalse(HrisPermission::roleHas('admin', 'unknown.permission'));
    }

    public function test_a_new_role_is_granted_through_config_only(): void
    {
        config(['hris.permissions' => ['mutasi.approve' => ['admin', 'hr_manager']] + config('hris.permissions')]);

        $this->assertTrue(HrisPermission::roleHas('hr_manager', HrisPermission::MUTASI_APPROVE));
        $this->assertFalse(HrisPermission::roleHas('hr_manager', HrisPermission::MUTASI_PREPARE));
    }
}
