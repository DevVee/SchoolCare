<?php

namespace Tests\Feature\Clinical;

use App\Models\User;
use Database\Seeders\AppointmentTimeSlotSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

abstract class ClinicalTestCase extends TestCase
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
}
