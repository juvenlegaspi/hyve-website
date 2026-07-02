<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdminRoleController extends Controller
{
    public function index(Request $request): View
    {
        $filters = [
            'search' => trim((string) $request->query('search', '')),
            'role' => (string) $request->query('role', 'all'),
            'status' => (string) $request->query('status', 'all'),
        ];

        $admins = User::query()
            ->whereIn('role', User::adminPanelRoles())
            ->when($filters['search'] !== '', function ($query) use ($filters) {
                $search = $filters['search'];

                $query->where(function ($builder) use ($search) {
                    $builder
                        ->where('username', 'like', '%'.$search.'%')
                        ->orWhere('first_name', 'like', '%'.$search.'%')
                        ->orWhere('last_name', 'like', '%'.$search.'%')
                        ->orWhere('email', 'like', '%'.$search.'%')
                        ->orWhere('number', 'like', '%'.$search.'%');

                    if (ctype_digit($search)) {
                        $builder->orWhereKey((int) $search);
                    }
                });
            })
            ->when($filters['role'] !== 'all', fn ($query) => $query->where('role', $filters['role']))
            ->when($filters['status'] === 'active', fn ($query) => $query->where('status', 0))
            ->when($filters['status'] === 'inactive', fn ($query) => $query->where('status', '!=', 0))
            ->orderByRaw("case when role = 'super_admin' then 1 when role = 'admin' then 2 when role = 'front_desk' then 3 when role = 'audit' then 4 else 5 end")
            ->orderBy('first_name')
            ->paginate(10)
            ->withQueryString();

        return view('admin.admin-roles.index', [
            'meta' => [
                'title' => 'Admin Roles | HYVE Admin',
                'description' => 'Manage admin and super admin accounts from one role control workspace.',
            ],
            'adminUser' => $request->user(),
            'admins' => $admins,
            'adminRows' => $admins->getCollection()->map(fn (User $user): array => $this->adminRowPayload($user, $request->user())),
            'filters' => $filters,
            'summary' => [
                'total_admins' => User::query()->whereIn('role', User::adminPanelRoles())->count(),
                'super_admins' => User::query()->where('role', User::ROLE_SUPER_ADMIN)->count(),
                'active_admins' => User::query()->whereIn('role', User::adminPanelRoles())->where('status', 0)->count(),
                'inactive_admins' => User::query()->whereIn('role', User::adminPanelRoles())->where('status', '!=', 0)->count(),
            ],
            'accessColumns' => config('admin_permissions.access_columns', []),
            'accessMatrix' => config('admin_permissions.access_matrix', []),
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        abort_unless(in_array((string) $user->role, User::adminPanelRoles(), true), 404);

        $validated = $request->validateWithBag('adminRoleUpdate', [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', Rule::unique('booking_users', 'email')->ignore($user->getKey())],
            'phone' => ['nullable', 'string', 'max:20'],
            'role' => ['required', Rule::in(User::adminPanelRoles())],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'user_id' => ['nullable'],
        ]);

        $actingUser = $request->user();
        $requestedStatus = $validated['status'] === 'active' ? 0 : 1;

        if ($actingUser && (int) $actingUser->getKey() === (int) $user->getKey()) {
            if ($validated['role'] !== (string) $user->role || $requestedStatus !== (int) $user->status) {
                return back()->withErrors([
                    'role' => 'You cannot change your own admin role or status from this page.',
                ], 'adminRoleUpdate');
            }
        }

        if ((string) $user->role === User::ROLE_SUPER_ADMIN) {
            $superAdminCount = User::query()->where('role', User::ROLE_SUPER_ADMIN)->count();

            if ($superAdminCount <= 1 && ($validated['role'] !== User::ROLE_SUPER_ADMIN || $requestedStatus !== 0)) {
                return back()->withErrors([
                    'role' => 'The last super admin must stay active to keep the admin panel secure.',
                ], 'adminRoleUpdate');
            }
        }

        $user->update([
            'first_name' => trim($validated['first_name']),
            'last_name' => trim($validated['last_name']),
            'email' => strtolower(trim($validated['email'])),
            'number' => trim((string) ($validated['phone'] ?? '')),
            'role' => $validated['role'],
            'status' => $requestedStatus,
        ]);

        return back()->with('admin_role_success', 'Admin role settings updated successfully.');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validateWithBag('adminRoleStore', [
            'username' => ['required', 'string', 'min:3', 'max:30', 'alpha_dash', Rule::unique('booking_users', 'username')],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', Rule::unique('booking_users', 'email')],
            'phone' => ['nullable', 'string', 'max:20'],
            'password' => ['required', 'string', 'min:8'],
            'role' => ['required', Rule::in(User::adminPanelRoles())],
        ]);

        User::query()->create([
            'username' => strtolower(trim($validated['username'])),
            'first_name' => trim($validated['first_name']),
            'last_name' => trim($validated['last_name']),
            'email' => strtolower(trim($validated['email'])),
            'number' => trim((string) ($validated['phone'] ?? '')),
            'password' => Hash::make($validated['password']),
            'status' => 0,
            'role' => $validated['role'],
        ]);

        return back()->with('admin_role_success', $this->roleLabel($validated['role']).' account created successfully.');
    }

    /**
     * @return array<string, mixed>
     */
    private function adminRowPayload(User $user, ?User $actingUser): array
    {
        $isSelf = $actingUser && (int) $actingUser->getKey() === (int) $user->getKey();

        return [
            'id' => $user->getKey(),
            'name' => $user->name,
            'initials' => $this->initials($user),
            'username' => (string) $user->username,
            'first_name' => (string) $user->first_name,
            'last_name' => (string) $user->last_name,
            'email' => (string) $user->email,
            'phone' => (string) $user->phone,
            'role' => $this->roleLabel((string) $user->role),
            'role_key' => (string) $user->role,
            'status' => (int) $user->status === 0 ? 'Active' : 'Inactive',
            'status_key' => (int) $user->status === 0 ? 'active' : 'inactive',
            'joined_at' => optional($user->created_at)->format('M j, Y'),
            'last_active' => optional($user->updated_at)->format('M j, Y') ?? '--',
            'is_self' => $isSelf,
            'update_url' => route('admin.admin-roles.update', $user),
        ];
    }

    private function roleLabel(string $role): string
    {
        return ucwords(str_replace('_', ' ', $role));
    }

    private function initials(User $user): string
    {
        $first = strtoupper(substr(trim((string) $user->first_name), 0, 1));
        $last = strtoupper(substr(trim((string) $user->last_name), 0, 1));

        return trim($first.$last) !== '' ? $first.$last : 'AD';
    }

}
