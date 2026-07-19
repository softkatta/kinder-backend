<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\User;
use App\Services\Audit\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use SoftKatta\Licensing\Services\LicenseService;

class UserController extends Controller
{
    public function __construct(
        private readonly AuditLogService $audit,
        private readonly LicenseService $license,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = User::query()->with('roles')->orderBy('name');

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        if ($role = $request->query('role')) {
            $query->whereHas('roles', fn ($q) => $q->where('name', $role));
        }

        $rows = $query->get()->map(fn (User $user) => $this->toRow($user));

        return ApiResponse::success($rows);
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $this->license->assertWithinLimit('max_users', User::query()->count());
        } catch (\RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 403);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:120', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:30'],
            'password' => ['required', 'string', 'min:8'],
            'is_active' => ['sometimes', 'boolean'],
            'roles' => ['sometimes', 'array'],
            'roles.*' => ['string', 'max:40'],
        ]);

        $user = User::create([
            'tenant_id' => $request->user()?->tenant_id,
            'name' => $data['name'],
            'email' => strtolower(trim($data['email'])),
            'phone' => $data['phone'] ?? null,
            // Plain password — User model `hashed` cast hashes once.
            'password' => $data['password'],
            'is_active' => $data['is_active'] ?? true,
        ]);

        if (! empty($data['roles'])) {
            $user->roles()->sync(
                \App\Models\Role::query()->whereIn('name', $data['roles'])->pluck('id'),
            );
        }

        $this->audit->log(
            $request->user(),
            'user.created',
            "Created user {$user->email}",
            'user',
            $user->id,
            null,
            $request,
        );

        return ApiResponse::success($this->toRow($user->load('roles')), 'User created', 201);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:120'],
            'email' => ['sometimes', 'email', 'max:120', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:30'],
            'password' => ['nullable', 'string', 'min:8'],
            'is_active' => ['sometimes', 'boolean'],
            'roles' => ['sometimes', 'array'],
            'roles.*' => ['string', 'max:40'],
        ]);

        $user->update(array_filter([
            'name' => $data['name'] ?? null,
            'email' => isset($data['email']) ? strtolower(trim($data['email'])) : null,
            'phone' => $data['phone'] ?? null,
            'is_active' => array_key_exists('is_active', $data) ? (bool) $data['is_active'] : null,
            // Plain password — User model `hashed` cast hashes once.
            'password' => ! empty($data['password']) ? $data['password'] : null,
        ], fn ($v) => $v !== null));

        if (array_key_exists('roles', $data)) {
            $user->roles()->sync(
                \App\Models\Role::query()->whereIn('name', $data['roles'])->pluck('id'),
            );
        }

        return ApiResponse::success($this->toRow($user->fresh('roles')), 'User updated');
    }

    public function destroy(User $user): JsonResponse
    {
        if ($user->id === auth()->id()) {
            return ApiResponse::error('You cannot delete your own account.', 422);
        }

        $user->delete();

        return ApiResponse::success(null, 'Deleted');
    }

    /** @return array<string, mixed> */
    private function toRow(User $user): array
    {
        $roles = $user->roleNames();
        $primaryRole = match (true) {
            in_array('super_admin', $roles, true) => 'Super Admin',
            in_array('admin', $roles, true) => 'Admin',
            in_array('teacher', $roles, true) => 'Teacher',
            in_array('parent', $roles, true) => 'Parent',
            in_array('student', $roles, true) => 'Student',
            in_array('staff', $roles, true) => 'Staff',
            default => $roles[0] ?? 'User',
        };

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'role' => $primaryRole,
            'roles' => $roles,
            'status' => $user->is_active ? 'Active' : 'Inactive',
            'is_active' => $user->is_active,
        ];
    }
}
