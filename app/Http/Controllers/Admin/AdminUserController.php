<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BookingHeader;
use App\Models\BookingPayment;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdminUserController extends Controller
{
    public function index(Request $request): View
    {
        $filters = [
            'search' => trim((string) $request->query('search', '')),
            'role' => (string) $request->query('role', 'all'),
            'status' => (string) $request->query('status', 'all'),
        ];

        $users = User::query()
            ->withCount([
                'bookingHeaders',
                'bookingPayments',
            ])
            ->withSum([
                'bookingPayments as approved_payments_sum' => fn ($query) => $query->where('status', BookingPayment::STATUS_APPROVED),
            ], 'amount')
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
            ->paginate(12)
            ->withQueryString();

        $userSummary = [
            'member_count' => User::query()->where('role', User::ROLE_MEMBER)->count(),
            'admin_count' => User::query()->whereIn('role', User::adminPanelRoles())->count(),
            'active_count' => User::query()->where('status', 0)->count(),
            'with_bookings_count' => User::query()->whereHas('bookingHeaders')->count(),
        ];

        return view('admin.users.index', [
            'meta' => [
                'title' => 'Users | HYVE Admin',
                'description' => 'Manage members and add new admin accounts from the super admin console.',
            ],
            'adminUser' => $request->user(),
            'users' => $users,
            'userRows' => $users->getCollection()->map(fn (User $user): array => $this->userRowPayload($user)),
            'filters' => $filters,
            'userSummary' => $userSummary,
        ]);
    }

    public function summary(User $user): JsonResponse
    {
        return response()->json([
            'user' => $this->userSummaryPayload($user),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
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

        return back()->with('admin_success', $this->roleLabel($validated['role']).' account created successfully.');
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'username' => ['required', 'string', 'min:3', 'max:30', 'alpha_dash', Rule::unique('booking_users', 'username')->ignore($user->getKey())],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', Rule::unique('booking_users', 'email')->ignore($user->getKey())],
            'phone' => ['nullable', 'string', 'max:20'],
            'role' => ['required', Rule::in(array_merge([User::ROLE_MEMBER], User::adminPanelRoles()))],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ]);

        $user->update([
            'username' => strtolower(trim($validated['username'])),
            'first_name' => trim($validated['first_name']),
            'last_name' => trim($validated['last_name']),
            'email' => strtolower(trim($validated['email'])),
            'number' => trim((string) ($validated['phone'] ?? '')),
            'role' => $validated['role'],
            'status' => $validated['status'] === 'active' ? 0 : 1,
        ]);

        return back()->with('admin_success', 'User account updated successfully.');
    }

    /**
     * @return array<string, mixed>
     */
    private function userRowPayload(User $user): array
    {
        $approvedTotal = (float) ($user->approved_payments_sum ?? 0);

        return [
            'id' => $user->getKey(),
            'name' => $user->name,
            'username' => (string) $user->username,
            'first_name' => (string) $user->first_name,
            'last_name' => (string) $user->last_name,
            'email' => (string) $user->email,
            'phone' => (string) $user->phone,
            'role' => $this->roleLabel((string) $user->role),
            'role_key' => (string) $user->role,
            'role_class' => $this->roleClass((string) $user->role),
            'status' => $this->statusLabel((int) $user->status),
            'status_key' => (int) $user->status === 0 ? 'active' : 'inactive',
            'status_class' => $this->statusClass((int) $user->status),
            'booking_count' => (int) ($user->booking_headers_count ?? 0),
            'payment_count' => (int) ($user->booking_payments_count ?? 0),
            'approved_total' => 'Php '.number_format($approvedTotal, 2),
            'joined_at' => optional($user->created_at)->format('M j, Y'),
            'summary_url' => route('admin.users.summary', $user),
            'update_url' => route('admin.users.update', $user),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function userSummaryPayload(User $user): array
    {
        $user->loadCount([
            'bookingHeaders',
            'bookingPayments',
        ]);

        $approvedPayments = BookingPayment::query()
            ->where('user_id', $user->getKey())
            ->where('status', BookingPayment::STATUS_APPROVED);

        $pendingPayments = BookingPayment::query()
            ->where('user_id', $user->getKey())
            ->where('status', BookingPayment::STATUS_PENDING);

        $bookings = BookingHeader::query()
            ->with(['details.hyveRoom', 'details.space'])
            ->where('user_id', $user->getKey())
            ->latest('created_at')
            ->limit(5)
            ->get()
            ->map(function (BookingHeader $header): array {
                $rooms = $header->details
                    ->map(fn ($detail) => $detail->hyveRoom?->room_name ?? $detail->space?->name ?? 'Room')
                    ->filter()
                    ->unique()
                    ->values()
                    ->all();

                return [
                    'id' => $header->getKey(),
                    'reference' => (string) $header->reference_no,
                    'rooms' => $rooms,
                    'status' => ucfirst((string) $header->status),
                    'payment_status' => ucfirst(str_replace('_', ' ', (string) $header->payment_status)),
                    'total_amount' => 'Php '.number_format((float) ($header->total_amount ?? 0), 2),
                    'balance_amount' => 'Php '.number_format((float) ($header->balance_amount ?? 0), 2),
                    'created_at' => optional($header->created_at)->format('M j, Y g:i A') ?? '--',
                ];
            })
            ->all();

        $payments = BookingPayment::query()
            ->with('bookingHeader')
            ->where('user_id', $user->getKey())
            ->orderByRaw('coalesce(paid_at, created_at) desc')
            ->limit(5)
            ->get()
            ->map(function (BookingPayment $payment): array {
                return [
                    'id' => $payment->getKey(),
                    'booking_reference' => (string) ($payment->bookingHeader?->reference_no ?? 'Booking'),
                    'type' => ucfirst(str_replace('_', ' ', (string) $payment->payment_type)),
                    'method' => ucfirst(str_replace('_', ' ', (string) $payment->payment_method)),
                    'status' => ucfirst((string) $payment->status),
                    'amount' => 'Php '.number_format((float) ($payment->amount ?? 0), 2),
                    'submitted_at' => optional($payment->paid_at)->format('M j, Y g:i A')
                        ?? optional($payment->created_at)->format('M j, Y g:i A')
                        ?? '--',
                ];
            })
            ->all();

        return [
            ...$this->userRowPayload($user),
            'joined_at_full' => optional($user->created_at)->format('F j, Y g:i A') ?? '--',
            'booking_count' => (int) ($user->booking_headers_count ?? 0),
            'payment_count' => (int) ($user->booking_payments_count ?? 0),
            'approved_total' => 'Php '.number_format((float) $approvedPayments->sum('amount'), 2),
            'pending_payment_count' => $pendingPayments->count(),
            'latest_bookings' => $bookings,
            'latest_payments' => $payments,
        ];
    }

    private function roleLabel(string $role): string
    {
        return ucwords(str_replace('_', ' ', $role));
    }

    private function roleClass(string $role): string
    {
        return match ($role) {
            User::ROLE_SUPER_ADMIN => 'admin-users-badge--super',
            User::ROLE_ADMIN => 'admin-users-badge--admin',
            User::ROLE_FRONT_DESK => 'admin-users-badge--frontdesk',
            User::ROLE_AUDIT => 'admin-users-badge--audit',
            default => 'admin-users-badge--member',
        };
    }

    private function statusLabel(int $status): string
    {
        return $status === 0 ? 'Active' : 'Inactive';
    }

    private function statusClass(int $status): string
    {
        return $status === 0 ? 'admin-users-badge--active' : 'admin-users-badge--inactive';
    }
}
