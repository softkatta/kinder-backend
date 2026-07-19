<?php

namespace App\Services\SoftKatta;

use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use SoftKatta\Licensing\Contracts\CreatesAdminUser;

class KindergartenAdminCreator implements CreatesAdminUser
{
    public function create(array $data): object
    {
        $tenant = Tenant::query()->first();
        if ($tenant === null) {
            $tenant = Tenant::query()->create([
                'name' => config('app.name', 'Kindergarten'),
                'slug' => 'default',
            ]);
        }

        $user = User::query()->updateOrCreate(
            ['email' => $data['email']],
            [
                'tenant_id' => $tenant->id,
                'name' => $data['name'],
                'password' => $data['password'],
                'is_active' => true,
            ],
        );

        $role = Role::query()->firstOrCreate(
            ['name' => 'super_admin'],
            ['label' => 'Super Admin'],
        );
        $user->roles()->sync([$role->id]);

        return $user;
    }
}
