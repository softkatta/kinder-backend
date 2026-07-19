<?php

namespace App\Services\SoftKatta;

use App\Models\Role;
use App\Models\User;
use SoftKatta\Licensing\Services\InstallOrchestrator;

/**
 * Kindergarten install facade — product-specific admin creation + package orchestrator.
 */
class InstallService
{
    public function __construct(
        private InstallOrchestrator $orchestrator,
        private KindergartenAdminCreator $adminCreator,
    ) {}

    public function status(): array
    {
        return $this->orchestrator->status();
    }

    public function requirements(): array
    {
        return $this->orchestrator->requirements();
    }

    public function configureDatabase(array $data): array
    {
        return $this->orchestrator->configureDatabase($data);
    }

    public function configureCompanyApi(array $data): array
    {
        return $this->orchestrator->configureCompanyApi($data);
    }

    public function isCompanyApiConfigured(): bool
    {
        return $this->orchestrator->isCompanyApiConfigured();
    }

    public function createAdmin(array $data): User
    {
        /** @var User $user */
        $user = $this->orchestrator->createAdmin($this->adminCreator, $data);

        return $user;
    }

    public function migrate(): array
    {
        $result = $this->orchestrator->migrate();

        // Roles only — never seed a super admin (wizard creates admin).
        foreach ([
            ['name' => 'super_admin', 'label' => 'Super Admin'],
            ['name' => 'teacher', 'label' => 'Teacher'],
            ['name' => 'staff', 'label' => 'Staff'],
            ['name' => 'parent', 'label' => 'Parent'],
            ['name' => 'student', 'label' => 'Student'],
            ['name' => 'guest', 'label' => 'Guest'],
        ] as $role) {
            Role::query()->firstOrCreate(
                ['name' => $role['name']],
                ['label' => $role['label']],
            );
        }

        $result['roles_seeded'] = true;

        return $result;
    }

    public function downloadConfiguration(): array
    {
        return $this->orchestrator->downloadConfiguration();
    }

    public function complete(): array
    {
        return $this->orchestrator->complete();
    }
}
