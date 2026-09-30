<?php

namespace Tests\Feature\Parity;

use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\AppointmentTimeSlotSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

abstract class ParityTestCase extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(AppointmentTimeSlotSeeder::class);

        $this->admin = $this->userWithRole('administrator');
    }

    protected function userWithRole(string $role): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole($role);

        return $user;
    }

    protected function userWithPermissions(array $permissions): User
    {
        $role = Role::create(['name' => 'custom-'.uniqid(), 'guard_name' => 'web']);
        $role->givePermissionTo($permissions);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole($role);

        return $user;
    }

    /** Next Monday to Friday date at least one day ahead (the default clinic week). */
    protected function nextWeekday(int $daysAhead = 1): Carbon
    {
        $d = today()->addDays($daysAhead);
        while ($d->isWeekend()) {
            $d->addDay();
        }

        return $d;
    }
}
