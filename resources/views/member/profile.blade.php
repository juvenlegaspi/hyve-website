@extends('layouts.app')

@section('content')
    @php
        $member = auth()->user();
        $initials = strtoupper(substr((string) $member->first_name, 0, 1).substr((string) $member->last_name, 0, 1));
    @endphp

    <div class="site-shell">
        @include('partials.home.navigation')

        <main class="member-portal section-pad">
            <div class="section-wrap">
                @if (session('member_success'))
                    <div class="flash flash--success">{{ session('member_success') }}</div>
                @endif

                @if ($errors->any())
                    <div class="flash flash--error">
                        Please review your profile details.
                        <ul>
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <section class="member-bookings-page__intro">
                    <p class="eyebrow">My account</p>
                    <h1>Edit Profile</h1>
                    <p>Update your member details in a separate clean workspace.</p>
                </section>

                <section class="member-account-shell">
                    <div class="member-account-strip">
                        <div class="member-account-strip__identity">
                            <div class="member-account-strip__avatar">{{ $initials }}</div>
                            <div>
                                <strong>{{ $member->name }}</strong>
                                <span>{{ $member->email }}</span>
                            </div>
                        </div>

                        <div class="member-account-strip__links">
                            <a href="{{ route('member.index') }}" class="member-account-strip__link">My bookings</a>
                            <a href="{{ route('member.profile.edit') }}" class="member-account-strip__link is-active">Edit profile</a>
                            <a href="{{ route('member.password.edit') }}" class="member-account-strip__link">Change password</a>
                        </div>
                    </div>
                </section>

                <article class="member-card member-card--narrow" id="profile-settings">
                    <div class="member-card__head">
                        <div>
                            <p class="eyebrow">Profile</p>
                            <h2>Edit details</h2>
                        </div>
                    </div>

                    <form action="{{ route('member.profile.update') }}" method="POST" class="member-form">
                        @csrf
                        @method('PATCH')

                        <label>
                            <span>Username</span>
                            <input type="text" name="username" value="{{ old('username', $member->username) }}">
                        </label>
                        <label>
                            <span>First name</span>
                            <input type="text" name="first_name" value="{{ old('first_name', $member->first_name) }}">
                        </label>
                        <label>
                            <span>Last name</span>
                            <input type="text" name="last_name" value="{{ old('last_name', $member->last_name) }}">
                        </label>
                        <label>
                            <span>Email</span>
                            <input type="email" name="email" value="{{ old('email', $member->email) }}">
                        </label>
                        <label>
                            <span>Phone</span>
                            <input type="text" name="phone" value="{{ old('phone', $member->phone) }}">
                        </label>

                        <button type="submit" class="button button--dark button--block">Save profile</button>
                    </form>
                </article>
            </div>
        </main>
    </div>
@endsection
