<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Orchid\Platform\Models\Role;
use Orchid\Platform\Models\Permission;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        // Assign operator permissions to the admin role
        $adminRole = Role::where('slug', 'admin')->first();

        if ($adminRole) {
            $permissions = [
                'platform.operator.dashboard',
                'platform.operator.orders',
                'platform.operator.deliveries',
                'platform.operator',
            ];

            foreach ($permissions as $perm) {
                $permission = Permission::firstOrCreate(['slug' => $perm]);
                $adminRole->permissions()->syncWithoutDetaching($permission);
            }
        }

        // (operator permissions already assigned above)
    }
}
