@php
    $member = auth()->user();
    $memberInitials = $member
        ? strtoupper(substr((string) $member->first_name, 0, 1).substr((string) $member->last_name, 0, 1))
        : 'HY';
@endphp

<div class="member-menu" data-member-menu>
    <button type="button" class="member-menu__toggle" data-member-toggle aria-expanded="false">
        <span>{{ $memberInitials }}</span>
    </button>

    <div class="member-menu__panel hidden" data-member-panel>
        <div class="member-menu__card">
            <p class="member-menu__name">{{ $member->name }}</p>
            <p class="member-menu__email">{{ $member->email }}</p>
        </div>

        <div class="member-menu__links">
            @if ($member->isAdmin())
                <a href="{{ route('admin.dashboard') }}" class="member-menu__link">Admin dashboard</a>
            @endif
            <a href="{{ route('member.index') }}" class="member-menu__link">My bookings</a>
            <a href="{{ route('member.profile.edit') }}" class="member-menu__link">Edit profile</a>
            <a href="{{ route('member.password.edit') }}" class="member-menu__link">Change password</a>
            <a href="{{ route('member.index') }}#booking-history" class="member-menu__link">Booking history</a>
        </div>

        <form action="{{ route('logout') }}" method="POST">
            @csrf
            <button type="submit" class="member-menu__logout">Log out</button>
        </form>
    </div>
</div>
