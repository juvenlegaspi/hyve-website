@extends('layouts.admin')

@section('content')
    @php
        $accessLabels = [
            'full' => 'Full',
            'read' => 'Read only',
            'none' => 'No access',
        ];

        $accessBadgeClasses = [
            'full' => 'admin-roles-access-badge admin-roles-access-badge--full',
            'read' => 'admin-roles-access-badge admin-roles-access-badge--read',
            'none' => 'admin-roles-access-badge admin-roles-access-badge--none',
        ];

        $storeErrors = $errors->adminRoleStore;
        $updateErrors = $errors->adminRoleUpdate;
    @endphp

    <style>
        .admin-roles-shell {
            display: grid;
            gap: 1.35rem;
        }

        .admin-roles-card {
            border: 1px solid #dfe7d8;
            border-radius: 1.35rem;
            background: #fff;
            box-shadow: 0 18px 42px rgba(17, 28, 24, 0.05);
        }

        .admin-roles-button,
        .admin-roles-button--soft {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 0.95rem;
            padding: 0.82rem 1.15rem;
            font-size: 0.82rem;
            font-weight: 700;
            line-height: 1;
        }

        .admin-roles-button {
            border: 1px solid #44793b;
            background: #44793b;
            color: #fff;
        }

        .admin-roles-button--soft {
            border: 1px solid #dfe7d8;
            background: #fff;
            color: #5f6f67;
        }

        .admin-roles-toolbar {
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
            align-items: center;
        }

        .admin-roles-toolbar__search,
        .admin-roles-toolbar__select {
            border: 1px solid #dfe7d8;
            border-radius: 0.95rem;
            background: #fff;
            color: #173029;
            font-size: 0.82rem;
            line-height: 1.3;
            padding: 0.8rem 0.95rem;
        }

        .admin-roles-toolbar__search {
            min-width: min(20rem, 100%);
            flex: 1 1 16rem;
        }

        .admin-roles-toolbar__select {
            min-width: 10rem;
        }

        .admin-roles-access-table,
        .admin-roles-accounts-table {
            width: 100%;
            border-collapse: collapse;
        }

        .admin-roles-access-table thead th,
        .admin-roles-accounts-table thead th {
            padding: 0 0 0.95rem;
            text-align: left;
            font-size: 0.72rem;
            font-weight: 700;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            color: #a8a294;
        }

        .admin-roles-access-table tbody td,
        .admin-roles-accounts-table tbody td {
            padding: 0.95rem 0;
            border-top: 1px solid #edf1e9;
            vertical-align: middle;
        }

        .admin-roles-access-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 999px;
            padding: 0.35rem 0.68rem;
            font-size: 0.68rem;
            font-weight: 700;
            line-height: 1;
            white-space: nowrap;
        }

        .admin-roles-access-badge--full {
            background: #eef7df;
            color: #3d6d34;
        }

        .admin-roles-access-badge--read {
            background: #fff1d9;
            color: #a36f1f;
        }

        .admin-roles-access-badge--none {
            background: #fff1f0;
            color: #c03e34;
        }

        .admin-roles-role-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 999px;
            padding: 0.38rem 0.72rem;
            font-size: 0.68rem;
            font-weight: 700;
            line-height: 1;
        }

        .admin-roles-role-badge--super {
            background: #eef7df;
            color: #3d6d34;
        }

        .admin-roles-role-badge--admin {
            background: #edf5dc;
            color: #365c33;
        }

        .admin-roles-role-badge--frontdesk {
            background: #fff1d9;
            color: #a36f1f;
        }

        .admin-roles-role-badge--audit {
            background: #eef2f6;
            color: #556474;
        }

        .admin-roles-avatar {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 2rem;
            height: 2rem;
            border-radius: 999px;
            background: #eef5dc;
            color: #46683c;
            font-size: 0.74rem;
            font-weight: 800;
            flex-shrink: 0;
        }

        .admin-roles-account {
            display: flex;
            align-items: center;
            gap: 0.85rem;
        }

        .admin-roles-account__copy {
            display: grid;
            gap: 0.16rem;
        }

        .admin-roles-account__copy strong {
            color: #132320;
            font-size: 0.88rem;
        }

        .admin-roles-account__copy span {
            color: #7f877d;
            font-size: 0.78rem;
        }

        .admin-roles-edit-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 2.4rem;
            height: 2.4rem;
            border-radius: 0.8rem;
            border: 1px solid #dfe7d8;
            background: #fff;
            color: #28443a;
        }

        .admin-roles-status-note {
            color: #7f877d;
            font-size: 0.78rem;
        }

        .admin-roles-modal {
            position: fixed;
            inset: 0;
            z-index: 90;
            display: none;
            align-items: flex-start;
            justify-content: center;
            overflow-y: auto;
            padding: 1rem;
        }

        .admin-roles-modal__backdrop {
            position: absolute;
            inset: 0;
            border: 0;
            background: rgba(18, 24, 21, 0.38);
            backdrop-filter: blur(10px);
        }

        .admin-roles-modal__card {
            position: relative;
            z-index: 1;
            width: min(36rem, 100%);
            max-height: calc(100vh - 2rem);
            overflow-y: auto;
            border: 1px solid rgba(17, 52, 44, 0.1);
            border-radius: 1.4rem;
            background: rgba(255, 251, 245, 0.98);
            box-shadow: 0 28px 80px rgba(17, 28, 24, 0.18);
            padding: 1.25rem;
        }

        .admin-roles-modal__top {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 1rem;
        }

        .admin-roles-modal__eyebrow {
            margin: 0;
            color: #9d7832;
            font-size: 0.7rem;
            font-weight: 800;
            letter-spacing: 0.18em;
            text-transform: uppercase;
        }

        .admin-roles-modal__title {
            margin: 0.4rem 0 0;
            color: #132320;
            font-size: 1.28rem;
            font-weight: 700;
            line-height: 1.15;
        }

        .admin-roles-modal__subtitle {
            margin: 0.3rem 0 0;
            color: #837d73;
            font-size: 0.82rem;
            line-height: 1.55;
        }

        .admin-roles-modal__close {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 2.3rem;
            height: 2.3rem;
            border: 1px solid rgba(17, 52, 44, 0.08);
            border-radius: 999px;
            background: #fff;
            color: #274239;
            font-size: 1.15rem;
            line-height: 1;
        }

        @media (max-width: 900px) {
            .admin-roles-shell {
                gap: 1rem;
            }
        }
    </style>

    <div class="admin-roles-shell">
        <section class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h1 class="text-[2rem] font-semibold tracking-[-0.05em] text-[#132320]">Admin Roles</h1>
                <p class="mt-1 text-[0.92rem] text-[#8b897f]">
                    {{ count($accessColumns) }} role templates with separate access levels — manage who can see and do what
                </p>
            </div>

            <button type="button" class="admin-roles-button" data-admin-roles-add-open>+ Add admin</button>
        </section>

        @if (session('admin_role_success'))
            <div class="rounded-[1rem] border border-[#d9ebcf] bg-[#f4faea] px-4 py-3 text-[0.82rem] font-semibold text-[#3f6a34]">
                {{ session('admin_role_success') }}
            </div>
        @endif

        @if ($storeErrors->any() || $updateErrors->any())
            <div class="rounded-[1rem] border border-[#f1d7d2] bg-[#fff5f3] px-4 py-3 text-[0.82rem] font-semibold text-[#ab4f43]">
                Please review the admin role form fields and try again.
            </div>
        @endif

        <section class="admin-roles-card p-5">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="text-[1.18rem] font-semibold text-[#132320]">Role access matrix</h2>
                    <p class="mt-1 text-[0.82rem] text-[#8b897f]">This matrix now matches the actual permission rules used by the admin routes and sidebar.</p>
                </div>
            </div>

            <div class="mt-5 overflow-x-auto">
                <table class="admin-roles-access-table min-w-[56rem]">
                    <thead>
                        <tr>
                            <th>Module</th>
                            @foreach ($accessColumns as $column)
                                <th>{{ $column['label'] }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($accessMatrix as $row)
                            <tr>
                                <td class="font-medium text-[#132320]">{{ $row['module'] }}</td>
                                @foreach ($accessColumns as $column)
                                    @php
                                        $level = $row[$column['key']] ?? 'none';
                                    @endphp
                                    <td>
                                        <span class="{{ $accessBadgeClasses[$level] ?? $accessBadgeClasses['none'] }}">
                                            {{ $accessLabels[$level] ?? 'No access' }}
                                        </span>
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        <section class="admin-roles-card p-5">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="text-[1.18rem] font-semibold text-[#132320]">Admin accounts</h2>
                    <p class="mt-1 text-[0.82rem] text-[#8b897f]">Review the actual admin accounts below and update their access from one place.</p>
                </div>

                <span class="text-[0.82rem] text-[#8b897f]">{{ $summary['total_admins'] }} active role record{{ $summary['total_admins'] === 1 ? '' : 's' }}</span>
            </div>

            <form method="GET" class="admin-roles-toolbar mt-4">
                <input type="text" name="search" value="{{ $filters['search'] ?? '' }}" class="admin-roles-toolbar__search" placeholder="Search name, username, email, phone, or ID...">
                <select name="role" class="admin-roles-toolbar__select">
                    <option value="all" @selected(($filters['role'] ?? 'all') === 'all')>All roles</option>
                    <option value="admin" @selected(($filters['role'] ?? '') === 'admin')>Admins</option>
                    <option value="front_desk" @selected(($filters['role'] ?? '') === 'front_desk')>Front desk</option>
                    <option value="audit" @selected(($filters['role'] ?? '') === 'audit')>Audit</option>
                    <option value="super_admin" @selected(($filters['role'] ?? '') === 'super_admin')>Super admins</option>
                </select>
                <select name="status" class="admin-roles-toolbar__select">
                    <option value="all" @selected(($filters['status'] ?? 'all') === 'all')>All status</option>
                    <option value="active" @selected(($filters['status'] ?? '') === 'active')>Active</option>
                    <option value="inactive" @selected(($filters['status'] ?? '') === 'inactive')>Inactive</option>
                </select>
                <button type="submit" class="admin-roles-button--soft">Filter</button>
            </form>

            <div class="mt-5 overflow-x-auto">
                <table class="admin-roles-accounts-table min-w-[54rem]">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Last active</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($adminRows as $row)
                            <tr
                                data-admin-role-row
                                data-update-url="{{ $row['update_url'] }}"
                                data-first-name="{{ $row['first_name'] }}"
                                data-last-name="{{ $row['last_name'] }}"
                                data-email="{{ $row['email'] }}"
                                data-phone="{{ $row['phone'] }}"
                                data-role-key="{{ $row['role_key'] }}"
                                data-status-key="{{ $row['status_key'] }}"
                                data-is-self="{{ $row['is_self'] ? '1' : '0' }}"
                            >
                                <td>
                                    <div class="admin-roles-account">
                                        <span class="admin-roles-avatar">{{ $row['initials'] }}</span>
                                        <div class="admin-roles-account__copy">
                                            <strong>{{ $row['name'] }}{{ $row['is_self'] ? ' (You)' : '' }}</strong>
                                            <span>{{ $row['username'] }}</span>
                                        </div>
                                    </div>
                                </td>
                                <td class="text-[0.84rem] text-[#4f5d54]">{{ $row['email'] }}</td>
                                <td>
                                    <span class="admin-roles-role-badge {{ $row['role_key'] === 'super_admin' ? 'admin-roles-role-badge--super' : ($row['role_key'] === 'front_desk' ? 'admin-roles-role-badge--frontdesk' : ($row['role_key'] === 'audit' ? 'admin-roles-role-badge--audit' : 'admin-roles-role-badge--admin')) }}">
                                        {{ $row['role'] }}
                                    </span>
                                    <div class="admin-roles-status-note mt-2">{{ $row['status'] }}</div>
                                </td>
                                <td class="text-[0.84rem] text-[#4f5d54]">{{ $row['last_active'] }}</td>
                                <td class="text-right">
                                    <button type="button" class="admin-roles-edit-button" data-admin-role-edit aria-label="Edit admin">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                            <path d="M4 20H8L18.5 9.5C19.3284 8.67157 19.3284 7.32843 18.5 6.5V6.5C17.6716 5.67157 16.3284 5.67157 15.5 6.5L5 17V20Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                                            <path d="M13.5 8.5L16.5 11.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                                        </svg>
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="py-8 text-center text-[#8f897d]">No admin accounts matched your search and filters.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-6">
                {{ $admins->links() }}
            </div>
        </section>
    </div>

    <div class="admin-roles-modal" data-admin-roles-add-modal @if ($storeErrors->any()) style="display:flex;" @endif>
        <button type="button" class="admin-roles-modal__backdrop" data-admin-roles-add-close aria-label="Close add admin modal"></button>

        <div class="admin-roles-modal__card">
            <div class="admin-roles-modal__top">
                <div>
                    <p class="admin-roles-modal__eyebrow">Admin account</p>
                    <h2 class="admin-roles-modal__title">Add admin</h2>
                    <p class="admin-roles-modal__subtitle">Create a new admin account and choose if this person should be a regular admin or a super admin.</p>
                </div>

                <button type="button" class="admin-roles-modal__close" data-admin-roles-add-close aria-label="Close add admin modal">&times;</button>
            </div>

            <form action="{{ route('admin.admin-roles.store') }}" method="POST" class="mt-5 grid gap-3">
                @csrf
                <input type="text" name="username" value="{{ old('username') }}" placeholder="Username" class="rounded-[0.85rem] border border-[#dfe7d8] px-3.5 py-2.5 text-[0.82rem]">
                <input type="text" name="first_name" value="{{ old('first_name') }}" placeholder="First name" class="rounded-[0.85rem] border border-[#dfe7d8] px-3.5 py-2.5 text-[0.82rem]">
                <input type="text" name="last_name" value="{{ old('last_name') }}" placeholder="Last name" class="rounded-[0.85rem] border border-[#dfe7d8] px-3.5 py-2.5 text-[0.82rem]">
                <input type="email" name="email" value="{{ old('email') }}" placeholder="Email address" class="rounded-[0.85rem] border border-[#dfe7d8] px-3.5 py-2.5 text-[0.82rem]">
                <input type="text" name="phone" value="{{ old('phone') }}" placeholder="Phone number" class="rounded-[0.85rem] border border-[#dfe7d8] px-3.5 py-2.5 text-[0.82rem]">
                <select name="role" class="rounded-[0.85rem] border border-[#dfe7d8] px-3.5 py-2.5 text-[0.82rem] text-[#173029]">
                    <option value="admin" @selected(old('role', 'admin') === 'admin')>Admin</option>
                    <option value="front_desk" @selected(old('role') === 'front_desk')>Front Desk</option>
                    <option value="audit" @selected(old('role') === 'audit')>Audit</option>
                    <option value="super_admin" @selected(old('role') === 'super_admin')>Super Admin</option>
                </select>
                <input type="text" name="password" value="{{ old('password') }}" placeholder="Temporary password" class="rounded-[0.85rem] border border-[#dfe7d8] px-3.5 py-2.5 text-[0.82rem]">

                <button type="submit" class="mt-2 rounded-[0.9rem] bg-[#3f7b3d] px-4 py-2.5 text-[0.82rem] font-semibold text-white">
                    Create admin account
                </button>
            </form>
        </div>
    </div>

    <div class="admin-roles-modal" data-admin-roles-edit-modal>
        <button type="button" class="admin-roles-modal__backdrop" data-admin-roles-edit-close aria-label="Close edit admin modal"></button>

        <div class="admin-roles-modal__card">
            <div class="admin-roles-modal__top">
                <div>
                    <p class="admin-roles-modal__eyebrow">Role settings</p>
                    <h2 class="admin-roles-modal__title">Update admin access</h2>
                    <p class="admin-roles-modal__subtitle" data-admin-roles-edit-subtitle>Review role and account access for the selected admin.</p>
                </div>

                <button type="button" class="admin-roles-modal__close" data-admin-roles-edit-close aria-label="Close edit admin modal">&times;</button>
            </div>

            <form method="POST" class="mt-5 grid gap-3" data-admin-roles-edit-form>
                @csrf
                @method('PATCH')
                <input type="hidden" name="user_id" value="{{ old('user_id') }}">
                <input type="text" name="first_name" placeholder="First name" class="rounded-[0.85rem] border border-[#dfe7d8] px-3.5 py-2.5 text-[0.82rem]">
                <input type="text" name="last_name" placeholder="Last name" class="rounded-[0.85rem] border border-[#dfe7d8] px-3.5 py-2.5 text-[0.82rem]">
                <input type="email" name="email" placeholder="Email address" class="rounded-[0.85rem] border border-[#dfe7d8] px-3.5 py-2.5 text-[0.82rem]">
                <input type="text" name="phone" placeholder="Phone number" class="rounded-[0.85rem] border border-[#dfe7d8] px-3.5 py-2.5 text-[0.82rem]">
                <select name="role" class="rounded-[0.85rem] border border-[#dfe7d8] px-3.5 py-2.5 text-[0.82rem] text-[#173029]">
                    <option value="admin">Admin</option>
                    <option value="front_desk">Front Desk</option>
                    <option value="audit">Audit</option>
                    <option value="super_admin">Super Admin</option>
                </select>
                <select name="status" class="rounded-[0.85rem] border border-[#dfe7d8] px-3.5 py-2.5 text-[0.82rem] text-[#173029]">
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>
                <p class="rounded-[0.9rem] border border-dashed border-[#d8ddd2] bg-[#fcfdf9] px-3.5 py-3 text-[0.77rem] leading-6 text-[#7b8478]" data-admin-roles-edit-note>
                    Role updates will apply immediately after saving.
                </p>

                <button type="submit" class="mt-2 rounded-[0.9rem] bg-[#3f7b3d] px-4 py-2.5 text-[0.82rem] font-semibold text-white">
                    Save changes
                </button>
            </form>
        </div>
    </div>

    <script>
        (() => {
            const addModal = document.querySelector('[data-admin-roles-add-modal]');
            const editModal = document.querySelector('[data-admin-roles-edit-modal]');
            const editForm = document.querySelector('[data-admin-roles-edit-form]');

            if (!addModal || !editModal || !editForm) {
                return;
            }

            const editSubtitle = editModal.querySelector('[data-admin-roles-edit-subtitle]');
            const editNote = editModal.querySelector('[data-admin-roles-edit-note]');
            const updateBagHasErrors = @json($updateErrors->any());
            const oldEditUserId = @json(old('user_id'));

            const lockScroll = () => {
                document.body.style.overflow = 'hidden';
                document.documentElement.style.overflow = 'hidden';
            };

            const unlockScroll = () => {
                if (addModal.style.display === 'none' && editModal.style.display === 'none') {
                    document.body.style.overflow = '';
                    document.documentElement.style.overflow = '';
                }
            };

            const openAddModal = () => {
                addModal.style.display = 'flex';
                lockScroll();
            };

            const closeAddModal = () => {
                addModal.style.display = 'none';
                unlockScroll();
            };

            const openEditModal = (row) => {
                editForm.action = row.dataset.updateUrl || '';
                editForm.querySelector('input[name="first_name"]').value = row.dataset.firstName || '';
                editForm.querySelector('input[name="last_name"]').value = row.dataset.lastName || '';
                editForm.querySelector('input[name="email"]').value = row.dataset.email || '';
                editForm.querySelector('input[name="phone"]').value = row.dataset.phone || '';
                editForm.querySelector('input[name="user_id"]').value = row.dataset.updateUrl ? (row.dataset.updateUrl.split('/').pop() || '') : '';
                editForm.querySelector('select[name="role"]').value = row.dataset.roleKey || 'admin';
                editForm.querySelector('select[name="status"]').value = row.dataset.statusKey || 'active';

                const isSelf = row.dataset.isSelf === '1';

                editSubtitle.textContent = isSelf
                    ? 'This is your own account. You can review the data here, but role and status changes are locked for safety.'
                    : 'Update this admin account role, contact details, and access status.';
                editNote.textContent = isSelf
                    ? 'Safety lock: your own role and status cannot be changed from this page.'
                    : 'Role updates will apply immediately after saving.';

                editForm.querySelectorAll('select[name="role"], select[name="status"]').forEach((field) => {
                    field.disabled = isSelf;
                });

                editModal.style.display = 'flex';
                lockScroll();
            };

            const closeEditModal = () => {
                editModal.style.display = 'none';
                unlockScroll();
            };

            document.querySelector('[data-admin-roles-add-open]')?.addEventListener('click', openAddModal);

            document.querySelectorAll('[data-admin-role-edit]').forEach((button) => {
                button.addEventListener('click', (event) => {
                    const row = event.currentTarget.closest('[data-admin-role-row]');

                    if (!row) {
                        return;
                    }

                    openEditModal(row);
                });
            });

            if (updateBagHasErrors && oldEditUserId) {
                const targetRow = document.querySelector(`[data-admin-role-row][data-update-url$="/${oldEditUserId}"]`);

                if (targetRow) {
                    openEditModal(targetRow);
                    editForm.querySelector('input[name="first_name"]').value = @json(old('first_name'));
                    editForm.querySelector('input[name="last_name"]').value = @json(old('last_name'));
                    editForm.querySelector('input[name="email"]').value = @json(old('email'));
                    editForm.querySelector('input[name="phone"]').value = @json(old('phone'));
                    editForm.querySelector('select[name="role"]').value = @json(old('role', 'admin'));
                    editForm.querySelector('select[name="status"]').value = @json(old('status', 'active'));
                }
            }

            addModal.querySelectorAll('[data-admin-roles-add-close]').forEach((button) => {
                button.addEventListener('click', closeAddModal);
            });

            editModal.querySelectorAll('[data-admin-roles-edit-close]').forEach((button) => {
                button.addEventListener('click', closeEditModal);
            });

            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape' && addModal.style.display !== 'none') {
                    closeAddModal();
                }

                if (event.key === 'Escape' && editModal.style.display !== 'none') {
                    closeEditModal();
                }
            });
        })();
    </script>
@endsection
