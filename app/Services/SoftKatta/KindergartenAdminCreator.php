<?php

namespace App\Services\SoftKatta;

use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use SoftKatta\Licensing\Contracts\CreatesAdminUser;

class KindergartenAdminCreator implements CreatesAdminUser
{
    public function create(array $data): object
    {
        $name = trim((string) ($data['name'] ?? ''));
        $email = strtolower(trim((string) ($data['email'] ?? '')));
        $password = (string) ($data['password'] ?? '');

        if ($name === '' || $email === '' || strlen($password) < 8) {
            throw new RuntimeException('Administrator name, email, and password (min 8 characters) are required.');
        }

        $tenant = Tenant::query()->first();
        if ($tenant === null) {
            $tenant = Tenant::query()->create([
                'name' => config('app.name', 'Kindergarten'),
                'slug' => 'default',
                'is_active' => true,
            ]);
        }

        $role = Role::query()->firstOrCreate(
            ['name' => 'super_admin'],
            ['label' => 'Super Admin'],
        );

        $user = User::query()->firstOrNew(['email' => $email]);
        $user->tenant_id = $tenant->id;
        $user->name = $name;
        $user->email = $email;
        $user->is_active = true;
        // Plain password — User model `hashed` cast hashes once (do not Hash::make here).
        $user->password = $password;
        $user->save();

        $user->roles()->sync([$role->id]);
        $user->refresh();

        if (! Hash::check($password, (string) $user->getAuthPassword())) {
            throw new RuntimeException(
                'Administrator was saved but the password could not be verified. '
                .'Check APP_KEY is set, then re-run the Administrator step.'
            );
        }

        return $user;
    }
}
