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
                        Please review your password details.
                        <ul>
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <section class="member-bookings-page__intro">
                    <p class="eyebrow">My account</p>
                    <h1>Change Password</h1>
                    <p>Update your password in a separate secure workspace.</p>
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
                            <a href="{{ route('member.profile.edit') }}" class="member-account-strip__link">Edit profile</a>
                            <a href="{{ route('member.password.edit') }}" class="member-account-strip__link is-active">Change password</a>
                        </div>
                    </div>
                </section>

                <article class="member-card member-card--narrow" id="password-settings">
                    <div class="member-card__head">
                        <div>
                            <p class="eyebrow">Security</p>
                            <h2>Change password</h2>
                        </div>
                    </div>

                    <form action="{{ route('member.password.update') }}" method="POST" class="member-form">
                        @csrf
                        @method('PATCH')

                        <label>
                            <span>Current password</span>
                            <input type="password" name="current_password">
                        </label>
                        <label>
                            <span>New password</span>
                            <input type="password" name="password">
                        </label>
                        <label>
                            <span>Confirm password</span>
                            <input type="password" name="password_confirmation">
                        </label>

                        <button type="submit" class="button button--dark button--block">Update password</button>
                    </form>
                </article>
            </div>
        </main>
    </div>
@endsection
