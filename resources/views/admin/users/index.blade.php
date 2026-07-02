@extends('layouts.admin')

@section('content')
    <style>
        .admin-users-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 999px;
            padding: 0.38rem 0.72rem;
            font-size: 0.66rem;
            font-weight: 700;
            line-height: 1;
        }

        .admin-users-badge--super {
            background: #efe8ff;
            color: #6a49b6;
        }

        .admin-users-badge--admin {
            background: #edf5dc;
            color: #365c33;
        }

        .admin-users-badge--frontdesk {
            background: #fff1d9;
            color: #a36f1f;
        }

        .admin-users-badge--audit {
            background: #eef2f6;
            color: #556474;
        }

        .admin-users-badge--member {
            background: #eef2f6;
            color: #596975;
        }

        .admin-users-badge--active {
            background: #eef8df;
            color: #3f6a34;
        }

        .admin-users-badge--inactive {
            background: #fde9e5;
            color: #b14635;
        }

        .admin-users-toolbar {
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
            align-items: center;
        }

        .admin-users-toolbar__search,
        .admin-users-toolbar__select {
            border: 1px solid #dfe7d8;
            border-radius: 0.95rem;
            background: #fff;
            color: #173029;
            font-size: 0.82rem;
            line-height: 1.3;
            padding: 0.8rem 0.95rem;
        }

        .admin-users-toolbar__search {
            min-width: min(22rem, 100%);
            flex: 1 1 18rem;
        }

        .admin-users-toolbar__select {
            min-width: 10rem;
        }

        .admin-users-toolbar__button,
        .admin-users-toolbar__clear {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 0.95rem;
            padding: 0.82rem 1.15rem;
            font-size: 0.8rem;
            font-weight: 700;
        }

        .admin-users-toolbar__button {
            border: 1px solid #44793b;
            background: #44793b;
            color: #fff;
        }

        .admin-users-toolbar__clear {
            border: 1px solid #dfe7d8;
            background: #fff;
            color: #5f6f67;
        }

        .admin-users-table__row {
            cursor: pointer;
            transition: background-color 0.18s ease;
        }

        .admin-users-table__row:hover {
            background: #f9fbf5;
        }

        .admin-users-table__action {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 0.85rem;
            border: 1px solid #dfe7d8;
            background: #fff;
            color: #173029;
            font-size: 0.76rem;
            font-weight: 700;
            padding: 0.58rem 0.85rem;
        }

        .admin-users-table__name {
            display: grid;
            gap: 0.18rem;
        }

        .admin-users-table__name strong {
            color: #173029;
            font-size: 0.84rem;
        }

        .admin-users-table__name span {
            color: #8c867a;
            font-size: 0.72rem;
        }

        .admin-users-modal {
            position: fixed;
            inset: 0;
            z-index: 90;
            display: none;
            align-items: flex-start;
            justify-content: center;
            overflow-y: auto;
            padding: 1rem;
        }

        .admin-users-modal__backdrop {
            position: absolute;
            inset: 0;
            border: 0;
            background: rgba(18, 24, 21, 0.38);
            backdrop-filter: blur(10px);
        }

        .admin-users-modal__card {
            position: relative;
            z-index: 1;
            width: min(68rem, 100%);
            max-height: calc(100vh - 2rem);
            overflow-y: auto;
            border: 1px solid rgba(17, 52, 44, 0.1);
            border-radius: 1.4rem;
            background: rgba(255, 251, 245, 0.98);
            box-shadow: 0 28px 80px rgba(17, 28, 24, 0.18);
            padding: 1.25rem;
        }

        .admin-users-modal__top {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 1rem;
        }

        .admin-users-modal__eyebrow {
            margin: 0;
            color: #9d7832;
            font-size: 0.7rem;
            font-weight: 800;
            letter-spacing: 0.18em;
            text-transform: uppercase;
        }

        .admin-users-modal__title {
            margin: 0.4rem 0 0;
            color: #132320;
            font-size: 1.28rem;
            font-weight: 700;
            line-height: 1.15;
        }

        .admin-users-modal__subtitle {
            margin: 0.3rem 0 0;
            color: #837d73;
            font-size: 0.82rem;
            line-height: 1.55;
        }

        .admin-users-modal__close {
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

        .admin-users-modal__grid {
            display: grid;
            gap: 1rem;
            margin-top: 1rem;
        }

        .admin-users-modal__panel {
            border: 1px solid #e5e9e1;
            border-radius: 1rem;
            background: #fff;
            padding: 1rem;
        }

        .admin-users-modal__panel-title {
            margin: 0;
            color: #132320;
            font-size: 0.92rem;
            font-weight: 700;
        }

        .admin-users-modal__details {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 0.85rem 1rem;
            margin-top: 0.9rem;
        }

        .admin-users-modal__details dt {
            margin: 0 0 0.2rem;
            color: #9a948a;
            font-size: 0.68rem;
            font-weight: 800;
            letter-spacing: 0.1em;
            text-transform: uppercase;
        }

        .admin-users-modal__details dd {
            margin: 0;
            color: #173029;
            font-size: 0.84rem;
            font-weight: 600;
            line-height: 1.55;
        }

        .admin-users-modal__chips {
            display: inline-flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-top: 0.9rem;
        }

        .admin-users-modal__cards {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 0.8rem;
            margin-top: 0.95rem;
        }

        .admin-users-modal__stat {
            border: 1px solid #edf1ea;
            border-radius: 0.95rem;
            background: #fbfcf8;
            padding: 0.85rem 0.9rem;
        }

        .admin-users-modal__stat strong {
            display: block;
            color: #132320;
            font-size: 1rem;
            font-weight: 700;
        }

        .admin-users-modal__stat span {
            display: block;
            margin-top: 0.2rem;
            color: #7e887c;
            font-size: 0.74rem;
        }

        .admin-users-modal__list {
            margin-top: 0.95rem;
            display: grid;
            gap: 0.8rem;
        }

        .admin-users-modal__empty {
            border: 1px dashed #d7ddd1;
            border-radius: 0.9rem;
            background: #fbfcf8;
            padding: 1rem;
            color: #8f897d;
            font-size: 0.82rem;
            text-align: center;
        }

        .admin-users-modal__item {
            border: 1px solid #e7ece4;
            border-radius: 0.95rem;
            background: #fbfcf8;
            padding: 0.9rem;
        }

        .admin-users-modal__item-top {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 0.8rem;
        }

        .admin-users-modal__item-title {
            color: #132320;
            font-size: 0.86rem;
            font-weight: 700;
        }

        .admin-users-modal__item-copy {
            margin-top: 0.22rem;
            color: #5f6f67;
            font-size: 0.76rem;
            line-height: 1.55;
        }

        .admin-users-admin-modal__card {
            width: min(32rem, 100%);
        }

        .admin-users-edit-modal__card {
            width: min(34rem, 100%);
        }

        @media (max-width: 980px) {
            .admin-users-modal__details {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .admin-users-modal__cards {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 760px) {
            .admin-users-modal {
                padding: 0.7rem;
            }

            .admin-users-modal__card {
                max-height: calc(100vh - 1.4rem);
                padding: 1rem;
                border-radius: 1.05rem;
            }

            .admin-users-modal__details,
            .admin-users-modal__cards {
                grid-template-columns: minmax(0, 1fr);
            }
        }
    </style>

    <div>
        <p class="text-[0.68rem] font-bold uppercase tracking-[0.22em] text-[#b39a5a]">Super admin tools</p>
        <h1 class="mt-2 text-[1.65rem] font-semibold tracking-[-0.05em] text-[#132320]">Users and admins</h1>
        <p class="mt-1 text-[0.84rem] text-[#8b897f]">Search members fast, review their booking activity, and still create new admin accounts from one page.</p>
    </div>

    @if (session('admin_success'))
        <div class="mt-4 rounded-[1rem] border border-[#d9ebcf] bg-[#f4faea] px-4 py-3 text-[0.82rem] font-semibold text-[#3f6a34]">
            {{ session('admin_success') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="mt-4 rounded-[1rem] border border-[#f1d7d2] bg-[#fff5f3] px-4 py-3 text-[0.82rem] font-semibold text-[#ab4f43]">
            Please review the admin form fields and try again.
        </div>
    @endif

    <section class="mt-5 grid gap-3.5 md:grid-cols-2 xl:grid-cols-4">
        <article class="rounded-[0.95rem] border border-[#e0e4db] bg-white px-4 py-4">
            <p class="text-[0.82rem] text-[#9d978a]">Members</p>
            <strong class="mt-2 block text-[1.6rem] font-semibold leading-none text-[#132320]">{{ $userSummary['member_count'] }}</strong>
            <p class="mt-2 text-[0.78rem] text-[#5f6f67]">Registered member accounts</p>
        </article>
        <article class="rounded-[0.95rem] border border-[#e0e4db] bg-white px-4 py-4">
            <p class="text-[0.82rem] text-[#9d978a]">Admins</p>
            <strong class="mt-2 block text-[1.6rem] font-semibold leading-none text-[#132320]">{{ $userSummary['admin_count'] }}</strong>
            <p class="mt-2 text-[0.78rem] text-[#5f6f67]">Admin and super admin accounts</p>
        </article>
        <article class="rounded-[0.95rem] border border-[#e0e4db] bg-white px-4 py-4">
            <p class="text-[0.82rem] text-[#9d978a]">Active users</p>
            <strong class="mt-2 block text-[1.6rem] font-semibold leading-none text-[#132320]">{{ $userSummary['active_count'] }}</strong>
            <p class="mt-2 text-[0.78rem] text-[#3f7b3d]">Accounts currently marked active</p>
        </article>
        <article class="rounded-[0.95rem] border border-[#e0e4db] bg-white px-4 py-4">
            <p class="text-[0.82rem] text-[#9d978a]">Users with bookings</p>
            <strong class="mt-2 block text-[1.6rem] font-semibold leading-none text-[#132320]">{{ $userSummary['with_bookings_count'] }}</strong>
            <p class="mt-2 text-[0.78rem] text-[#9a7832]">Accounts already linked to bookings</p>
        </article>
    </section>

    <section class="mt-5">
        <article class="rounded-[1.25rem] border border-[#dfe7d8] bg-white p-4">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="text-[1.08rem] font-semibold text-[#132320]">All users</h2>
                    <p class="mt-1 text-[0.8rem] text-[#8b897f]">Click a user row to view bookings and payment activity.</p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <span class="rounded-full bg-[#f5f7f2] px-3 py-1 text-[0.72rem] font-semibold text-[#7b857a]">
                        {{ $users->total() }} result{{ $users->total() === 1 ? '' : 's' }}
                    </span>
                    <button type="button" class="admin-users-toolbar__button" data-admin-users-add-open>Add admin</button>
                </div>
            </div>

            <form method="GET" class="admin-users-toolbar mt-4">
                <input type="text" name="search" value="{{ $filters['search'] ?? '' }}" class="admin-users-toolbar__search" placeholder="Search ID, username, name, email, or phone...">
                <select name="role" class="admin-users-toolbar__select">
                    <option value="all" @selected(($filters['role'] ?? 'all') === 'all')>All roles</option>
                    <option value="member" @selected(($filters['role'] ?? '') === 'member')>Members</option>
                    <option value="admin" @selected(($filters['role'] ?? '') === 'admin')>Admins</option>
                    <option value="front_desk" @selected(($filters['role'] ?? '') === 'front_desk')>Front desk</option>
                    <option value="audit" @selected(($filters['role'] ?? '') === 'audit')>Audit</option>
                    <option value="super_admin" @selected(($filters['role'] ?? '') === 'super_admin')>Super admins</option>
                </select>
                <select name="status" class="admin-users-toolbar__select">
                    <option value="all" @selected(($filters['status'] ?? 'all') === 'all')>All status</option>
                    <option value="active" @selected(($filters['status'] ?? '') === 'active')>Active</option>
                    <option value="inactive" @selected(($filters['status'] ?? '') === 'inactive')>Inactive</option>
                </select>
                <button type="submit" class="admin-users-toolbar__button">Search</button>
                <a href="{{ route('admin.users.index') }}" class="admin-users-toolbar__clear">Clear</a>
            </form>

            <div class="mt-4 overflow-x-auto">
                <table class="min-w-full text-left text-[0.82rem]">
                    <thead class="text-[0.66rem] font-bold uppercase tracking-[0.12em] text-[#aaa59b]">
                        <tr>
                            <th class="px-2 py-3">ID</th>
                            <th class="px-2 py-3">User</th>
                            <th class="px-2 py-3">Role</th>
                            <th class="px-2 py-3">Status</th>
                            <th class="px-2 py-3">Bookings</th>
                            <th class="px-2 py-3">Payments</th>
                            <th class="px-2 py-3">Approved total</th>
                            <th class="px-2 py-3 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#eef1ea]">
                        @forelse ($userRows as $row)
                            <tr
                                class="admin-users-table__row"
                                data-admin-user-open
                                data-id="{{ $row['id'] }}"
                                data-summary-url="{{ $row['summary_url'] }}"
                                data-update-url="{{ $row['update_url'] }}"
                                data-username="{{ $row['username'] }}"
                                data-first-name="{{ $row['first_name'] }}"
                                data-last-name="{{ $row['last_name'] }}"
                                data-email="{{ $row['email'] }}"
                                data-phone="{{ $row['phone'] }}"
                                data-role-key="{{ $row['role_key'] }}"
                                data-status-key="{{ $row['status_key'] }}"
                            >
                                <td class="px-2 py-3 font-semibold text-[#1a2a26]">#{{ $row['id'] }}</td>
                                <td class="px-2 py-3">
                                    <div class="admin-users-table__name">
                                        <strong>{{ $row['name'] }}</strong>
                                        <span>{{ $row['username'] }} · {{ $row['email'] }}</span>
                                    </div>
                                </td>
                                <td class="px-2 py-3">
                                    <span class="admin-users-badge {{ $row['role_class'] }}">{{ $row['role'] }}</span>
                                </td>
                                <td class="px-2 py-3">
                                    <span class="admin-users-badge {{ $row['status_class'] }}">{{ $row['status'] }}</span>
                                </td>
                                <td class="px-2 py-3 text-[#5f6f67]">{{ $row['booking_count'] }}</td>
                                <td class="px-2 py-3 text-[#5f6f67]">{{ $row['payment_count'] }}</td>
                                <td class="px-2 py-3 font-semibold text-[#1a2a26]">{{ $row['approved_total'] }}</td>
                                <td class="px-2 py-3 text-right">
                                    <button type="button" class="admin-users-table__action" data-admin-user-edit> Edit </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-2 py-8 text-center text-[#8f897d]">No users matched your search and filters.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-6">
                {{ $users->links() }}
            </div>
        </article>
    </section>

    <div class="admin-users-modal" data-admin-users-modal>
        <button type="button" class="admin-users-modal__backdrop" data-admin-users-close aria-label="Close user modal"></button>

        <div class="admin-users-modal__card">
            <div class="admin-users-modal__top">
                <div>
                    <p class="admin-users-modal__eyebrow" data-admin-user-modal-eyebrow>User</p>
                    <h2 class="admin-users-modal__title" data-admin-user-modal-name>User name</h2>
                    <p class="admin-users-modal__subtitle" data-admin-user-modal-subtitle>Member summary</p>
                </div>

                <button type="button" class="admin-users-modal__close" data-admin-users-close aria-label="Close user modal">&times;</button>
            </div>

            <div class="admin-users-modal__grid">
                <section class="admin-users-modal__panel">
                    <h3 class="admin-users-modal__panel-title">Profile summary</h3>

                    <dl class="admin-users-modal__details">
                        <div>
                            <dt>Username</dt>
                            <dd data-admin-user-modal-username></dd>
                        </div>
                        <div>
                            <dt>Email</dt>
                            <dd data-admin-user-modal-email></dd>
                        </div>
                        <div>
                            <dt>Phone</dt>
                            <dd data-admin-user-modal-phone></dd>
                        </div>
                        <div>
                            <dt>Joined</dt>
                            <dd data-admin-user-modal-joined></dd>
                        </div>
                    </dl>

                    <div class="admin-users-modal__chips">
                        <span class="admin-users-badge" data-admin-user-modal-role></span>
                        <span class="admin-users-badge" data-admin-user-modal-status></span>
                    </div>

                    <div class="admin-users-modal__cards">
                        <div class="admin-users-modal__stat">
                            <strong data-admin-user-modal-bookings-count></strong>
                            <span>Total bookings</span>
                        </div>
                        <div class="admin-users-modal__stat">
                            <strong data-admin-user-modal-payments-count></strong>
                            <span>Total payment records</span>
                        </div>
                        <div class="admin-users-modal__stat">
                            <strong data-admin-user-modal-approved-total></strong>
                            <span>Approved payment total</span>
                        </div>
                    </div>
                </section>

                <section class="admin-users-modal__panel">
                    <h3 class="admin-users-modal__panel-title">Latest bookings</h3>
                    <div class="admin-users-modal__list" data-admin-user-modal-bookings></div>
                </section>

                <section class="admin-users-modal__panel">
                    <h3 class="admin-users-modal__panel-title">Latest payments</h3>
                    <div class="admin-users-modal__list" data-admin-user-modal-payments></div>
                </section>
            </div>
        </div>
    </div>

    <div class="admin-users-modal" data-admin-users-admin-modal @if ($errors->any()) style="display:flex;" @endif>
        <button type="button" class="admin-users-modal__backdrop" data-admin-users-admin-close aria-label="Close add admin modal"></button>

        <div class="admin-users-modal__card admin-users-admin-modal__card">
            <div class="admin-users-modal__top">
                <div>
                    <p class="admin-users-modal__eyebrow">Admin account</p>
                    <h2 class="admin-users-modal__title">Add admin</h2>
                    <p class="admin-users-modal__subtitle">Choose whether this new account should be a regular admin or a super admin.</p>
                </div>

                <button type="button" class="admin-users-modal__close" data-admin-users-admin-close aria-label="Close add admin modal">&times;</button>
            </div>

            <div class="admin-users-modal__grid">
                <section class="admin-users-modal__panel">
                    <form action="{{ route('admin.users.store') }}" method="POST" class="grid gap-3">
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
                </section>
            </div>
        </div>
    </div>

    <div class="admin-users-modal" data-admin-users-edit-modal>
        <button type="button" class="admin-users-modal__backdrop" data-admin-users-edit-close aria-label="Close edit user modal"></button>

        <div class="admin-users-modal__card admin-users-edit-modal__card">
            <div class="admin-users-modal__top">
                <div>
                    <p class="admin-users-modal__eyebrow">User account</p>
                    <h2 class="admin-users-modal__title">Edit user</h2>
                    <p class="admin-users-modal__subtitle">Update the selected user's profile, role, and status.</p>
                </div>

                <button type="button" class="admin-users-modal__close" data-admin-users-edit-close aria-label="Close edit user modal">&times;</button>
            </div>

            <div class="admin-users-modal__grid">
                <section class="admin-users-modal__panel">
                    <form method="POST" class="grid gap-3" data-admin-users-edit-form>
                        @csrf
                        @method('PATCH')

                        <input type="text" name="username" placeholder="Username" class="rounded-[0.85rem] border border-[#dfe7d8] px-3.5 py-2.5 text-[0.82rem]">
                        <input type="text" name="first_name" placeholder="First name" class="rounded-[0.85rem] border border-[#dfe7d8] px-3.5 py-2.5 text-[0.82rem]">
                        <input type="text" name="last_name" placeholder="Last name" class="rounded-[0.85rem] border border-[#dfe7d8] px-3.5 py-2.5 text-[0.82rem]">
                        <input type="email" name="email" placeholder="Email address" class="rounded-[0.85rem] border border-[#dfe7d8] px-3.5 py-2.5 text-[0.82rem]">
                        <input type="text" name="phone" placeholder="Phone number" class="rounded-[0.85rem] border border-[#dfe7d8] px-3.5 py-2.5 text-[0.82rem]">
                        <select name="role" class="rounded-[0.85rem] border border-[#dfe7d8] px-3.5 py-2.5 text-[0.82rem] text-[#173029]">
                            <option value="member">Member</option>
                            <option value="admin">Admin</option>
                            <option value="front_desk">Front Desk</option>
                            <option value="audit">Audit</option>
                            <option value="super_admin">Super Admin</option>
                        </select>
                        <select name="status" class="rounded-[0.85rem] border border-[#dfe7d8] px-3.5 py-2.5 text-[0.82rem] text-[#173029]">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>

                        <button type="submit" class="mt-2 rounded-[0.9rem] bg-[#3f7b3d] px-4 py-2.5 text-[0.82rem] font-semibold text-white">
                            Save changes
                        </button>
                    </form>
                </section>
            </div>
        </div>
    </div>

    <script>
        (() => {
            const modal = document.querySelector('[data-admin-users-modal]');
            const adminModal = document.querySelector('[data-admin-users-admin-modal]');
            const editModal = document.querySelector('[data-admin-users-edit-modal]');

            if (!modal || !adminModal || !editModal) {
                return;
            }

            const name = modal.querySelector('[data-admin-user-modal-name]');
            const eyebrow = modal.querySelector('[data-admin-user-modal-eyebrow]');
            const subtitle = modal.querySelector('[data-admin-user-modal-subtitle]');
            const username = modal.querySelector('[data-admin-user-modal-username]');
            const email = modal.querySelector('[data-admin-user-modal-email]');
            const phone = modal.querySelector('[data-admin-user-modal-phone]');
            const joined = modal.querySelector('[data-admin-user-modal-joined]');
            const role = modal.querySelector('[data-admin-user-modal-role]');
            const status = modal.querySelector('[data-admin-user-modal-status]');
            const bookingsCount = modal.querySelector('[data-admin-user-modal-bookings-count]');
            const paymentsCount = modal.querySelector('[data-admin-user-modal-payments-count]');
            const approvedTotal = modal.querySelector('[data-admin-user-modal-approved-total]');
            const bookingsWrap = modal.querySelector('[data-admin-user-modal-bookings]');
            const paymentsWrap = modal.querySelector('[data-admin-user-modal-payments]');
            const editForm = editModal.querySelector('[data-admin-users-edit-form]');

            const closeModal = () => {
                modal.style.display = 'none';
                document.body.style.overflow = '';
                document.documentElement.style.overflow = '';
            };

            const openAdminModal = () => {
                adminModal.style.display = 'flex';
                document.body.style.overflow = 'hidden';
                document.documentElement.style.overflow = 'hidden';
            };

            const closeAdminModal = () => {
                adminModal.style.display = 'none';

                if (modal.style.display === 'none' && editModal.style.display === 'none') {
                    document.body.style.overflow = '';
                    document.documentElement.style.overflow = '';
                }
            };

            const openEditModal = (row) => {
                if (!editForm || !row) {
                    return;
                }

                editForm.action = row.dataset.updateUrl || '';
                editForm.querySelector('input[name="username"]').value = row.dataset.username || '';
                editForm.querySelector('input[name="first_name"]').value = row.dataset.firstName || '';
                editForm.querySelector('input[name="last_name"]').value = row.dataset.lastName || '';
                editForm.querySelector('input[name="email"]').value = row.dataset.email || '';
                editForm.querySelector('input[name="phone"]').value = row.dataset.phone || '';
                editForm.querySelector('select[name="role"]').value = row.dataset.roleKey || 'member';
                editForm.querySelector('select[name="status"]').value = row.dataset.statusKey || 'active';

                editModal.style.display = 'flex';
                document.body.style.overflow = 'hidden';
                document.documentElement.style.overflow = 'hidden';
            };

            const closeEditModal = () => {
                editModal.style.display = 'none';

                if (modal.style.display === 'none' && adminModal.style.display === 'none') {
                    document.body.style.overflow = '';
                    document.documentElement.style.overflow = '';
                }
            };

            const badgeClass = (type, value) => {
                const text = String(value || '').toLowerCase();

                if (type === 'role') {
                    if (text.includes('super')) return 'admin-users-badge admin-users-badge--super';
                    if (text.includes('admin')) return 'admin-users-badge admin-users-badge--admin';
                    if (text.includes('front')) return 'admin-users-badge admin-users-badge--frontdesk';
                    if (text.includes('audit')) return 'admin-users-badge admin-users-badge--audit';

                    return 'admin-users-badge admin-users-badge--member';
                }

                return text.includes('active')
                    ? 'admin-users-badge admin-users-badge--active'
                    : 'admin-users-badge admin-users-badge--inactive';
            };

            const emptyState = (label) => `<div class="admin-users-modal__empty">${label}</div>`;

            const bookingCards = (bookings = []) => {
                if (!bookings.length) {
                    return emptyState('No bookings linked to this user yet.');
                }

                return bookings.map((booking) => `
                    <article class="admin-users-modal__item">
                        <div class="admin-users-modal__item-top">
                            <div>
                                <div class="admin-users-modal__item-title">${booking.reference || 'Booking'}</div>
                                <div class="admin-users-modal__item-copy">${Array.isArray(booking.rooms) && booking.rooms.length ? booking.rooms.join(', ') : 'Room booking'}</div>
                            </div>
                            <span class="admin-users-badge admin-users-badge--member">${booking.status || 'Pending'}</span>
                        </div>
                        <div class="admin-users-modal__item-copy">Payment: ${booking.payment_status || '--'}</div>
                        <div class="admin-users-modal__item-copy">Total: ${booking.total_amount || '--'} | Balance: ${booking.balance_amount || '--'}</div>
                        <div class="admin-users-modal__item-copy">${booking.created_at || '--'}</div>
                    </article>
                `).join('');
            };

            const paymentCards = (payments = []) => {
                if (!payments.length) {
                    return emptyState('No payment records linked to this user yet.');
                }

                return payments.map((payment) => `
                    <article class="admin-users-modal__item">
                        <div class="admin-users-modal__item-top">
                            <div>
                                <div class="admin-users-modal__item-title">Payment #${payment.id || '--'}</div>
                                <div class="admin-users-modal__item-copy">${payment.booking_reference || 'Booking'} · ${payment.amount || '--'}</div>
                            </div>
                            <span class="admin-users-badge ${String(payment.status || '').toLowerCase().includes('approved') ? 'admin-users-badge--active' : String(payment.status || '').toLowerCase().includes('reject') ? 'admin-users-badge--inactive' : 'admin-users-badge--member'}">${payment.status || 'Pending'}</span>
                        </div>
                        <div class="admin-users-modal__item-copy">${payment.type || '--'} · ${payment.method || '--'}</div>
                        <div class="admin-users-modal__item-copy">${payment.submitted_at || '--'}</div>
                    </article>
                `).join('');
            };

            const openModal = (user) => {
                eyebrow.textContent = `User #${user.id || '--'}`;
                name.textContent = user.name || 'User';
                subtitle.textContent = `${user.email || '--'} · ${user.phone || '--'}`;
                username.textContent = user.username || '--';
                email.textContent = user.email || '--';
                phone.textContent = user.phone || '--';
                joined.textContent = user.joined_at_full || user.joined_at || '--';
                role.textContent = user.role || '--';
                role.className = badgeClass('role', user.role_key || user.role);
                status.textContent = user.status || '--';
                status.className = badgeClass('status', user.status_key || user.status);
                bookingsCount.textContent = String(user.booking_count || 0);
                paymentsCount.textContent = String(user.payment_count || 0);
                approvedTotal.textContent = user.approved_total || 'Php 0.00';
                bookingsWrap.innerHTML = bookingCards(Array.isArray(user.latest_bookings) ? user.latest_bookings : []);
                paymentsWrap.innerHTML = paymentCards(Array.isArray(user.latest_payments) ? user.latest_payments : []);

                modal.style.display = 'flex';
                document.body.style.overflow = 'hidden';
                document.documentElement.style.overflow = 'hidden';
            };

            document.querySelectorAll('[data-admin-user-open]').forEach((row) => {
                row.querySelector('[data-admin-user-edit]')?.addEventListener('click', (event) => {
                    event.stopPropagation();
                    openEditModal(row);
                });

                row.addEventListener('click', async () => {
                    const summaryUrl = row.dataset.summaryUrl || '';

                    if (!summaryUrl) {
                        return;
                    }

                    try {
                        const response = await fetch(summaryUrl, {
                            headers: {
                                'Accept': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                        });

                        if (!response.ok) {
                            return;
                        }

                        const payload = await response.json();

                        if (!payload.user) {
                            return;
                        }

                        openModal(payload.user);
                    } catch (error) {
                        // Ignore modal fetch failures quietly.
                    }
                });
            });

            document.querySelector('[data-admin-users-add-open]')?.addEventListener('click', openAdminModal);

            modal.querySelectorAll('[data-admin-users-close]').forEach((button) => {
                button.addEventListener('click', closeModal);
            });

            adminModal.querySelectorAll('[data-admin-users-admin-close]').forEach((button) => {
                button.addEventListener('click', closeAdminModal);
            });

            editModal.querySelectorAll('[data-admin-users-edit-close]').forEach((button) => {
                button.addEventListener('click', closeEditModal);
            });

            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape' && modal.style.display !== 'none') {
                    closeModal();
                }

                if (event.key === 'Escape' && adminModal.style.display !== 'none') {
                    closeAdminModal();
                }

                if (event.key === 'Escape' && editModal.style.display !== 'none') {
                    closeEditModal();
                }
            });
        })();
    </script>
@endsection
