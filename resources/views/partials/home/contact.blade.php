<section id="contact" class="px-6 pb-24 pt-32 md:px-10 md:pt-36">
    <div class="section-shell-dark mx-auto max-w-7xl rounded-[2.7rem] text-white">
        <div class="grid gap-10 px-8 py-12 md:px-12 md:py-16 lg:grid-cols-[1fr_0.95fr]">
            <div>
                <p class="section-kicker text-[#d9b87d]">Contact HYVE</p>
                <h2 class="section-heading mt-5 max-w-2xl text-4xl md:text-5xl">
                    Ready for a workspace that helps you work well and meet well?
                </h2>
                <p class="mt-6 max-w-2xl text-lg leading-8 text-white/70">
                    Reach out to check availability, ask about rates, or arrange a visit. You can book directly for one-time or regular reservations, while monthly members can keep an account for easier monitoring and repeat requests.
                </p>

                <div class="mt-10 flex flex-col gap-4 sm:flex-row sm:flex-wrap">
                    <a href="tel:{{ $contact['phone_href'] }}" class="inline-flex items-center justify-center rounded-full bg-[#c49c5b] px-8 py-4 text-sm font-semibold uppercase tracking-[0.24em] text-[#18130f] transition hover:-translate-y-0.5 hover:bg-[#d9b87d]">
                        Call Now
                    </a>
                    <a href="mailto:{{ $contact['email'] }}?subject=HYVE%20Workspace%20Inquiry" class="inline-flex items-center justify-center rounded-full border border-white/20 px-8 py-4 text-sm font-semibold uppercase tracking-[0.24em] text-white transition hover:-translate-y-0.5 hover:border-[#d9b87d] hover:text-[#d9b87d]">
                        Email Us
                    </a>
                    <a href="{{ $contact['map_url'] }}" target="_blank" rel="noreferrer" class="inline-flex items-center justify-center rounded-full border border-white/20 px-8 py-4 text-sm font-semibold uppercase tracking-[0.24em] text-white transition hover:-translate-y-0.5 hover:border-[#d9b87d] hover:text-[#d9b87d]">
                        Open Map
                    </a>
                </div>
            </div>

            <div class="space-y-5">
                <div class="reveal rounded-[2rem] border border-white/10 bg-white/5 p-6 backdrop-blur">
                    <p class="micro-label text-[#d9b87d]">Guest Booking and Membership</p>
                    <h3 class="mt-3 text-2xl font-semibold">Book directly, or sign up if you want monthly-member access.</h3>
                    <p class="mt-4 leading-8 text-white/72">
                        One-time guests and regular renters can submit a booking right away. Registration and login are reserved for clients who want a monthly-member account with saved details and easier repeat booking.
                    </p>

                    <div class="mt-6 flex flex-col gap-4 sm:flex-row">
                        @auth
                            <a href="{{ route('bookings.index') }}" class="inline-flex items-center justify-center rounded-full bg-[#c49c5b] px-8 py-4 text-sm font-semibold uppercase tracking-[0.24em] text-[#18130f] transition hover:-translate-y-0.5 hover:bg-[#d9b87d]">
                                Open Booking Page
                            </a>
                        @else
                            <a href="{{ route('bookings.index') }}" class="inline-flex items-center justify-center rounded-full bg-[#c49c5b] px-8 py-4 text-sm font-semibold uppercase tracking-[0.24em] text-[#18130f] transition hover:-translate-y-0.5 hover:bg-[#d9b87d]">
                                Book Directly
                            </a>
                            <a href="{{ route('register') }}" class="inline-flex items-center justify-center rounded-full bg-[#c49c5b] px-8 py-4 text-sm font-semibold uppercase tracking-[0.24em] text-[#18130f] transition hover:-translate-y-0.5 hover:bg-[#d9b87d]">
                                Become a Member
                            </a>
                            <a href="{{ route('login') }}" class="inline-flex items-center justify-center rounded-full border border-white/20 px-8 py-4 text-sm font-semibold uppercase tracking-[0.24em] text-white transition hover:-translate-y-0.5 hover:border-[#d9b87d] hover:text-[#d9b87d]">
                                Member Log In
                            </a>
                        @endauth
                    </div>
                </div>

                <div class="reveal rounded-[2rem] bg-white/5 p-6">
                    <p class="micro-label text-[#d9b87d]">Booking Flow</p>
                    <ul class="mt-4 space-y-3 text-sm leading-7 text-white/72">
                        <li>1. Open the booking page and choose your preferred space, date, and time.</li>
                        <li>2. Submit your contact details directly for one-time or regular reservations.</li>
                        <li>3. Register and log in only if you want a monthly-member account for easier repeat booking.</li>
                    </ul>
                </div>

                <div class="grid gap-5 sm:grid-cols-2">
                    <div class="reveal rounded-[2rem] bg-white/5 p-6">
                        <p class="micro-label text-[#d9b87d]">Address</p>
                        <p class="mt-3 leading-8 text-white/78">{!! implode('<br>', $contact['address_lines']) !!}</p>
                    </div>
                    <div class="reveal rounded-[2rem] bg-white/5 p-6">
                        <p class="micro-label text-[#d9b87d]">Open Hours</p>
                        <p class="mt-3 leading-8 text-white/78">{!! implode('<br>', $contact['hours_lines']) !!}</p>
                    </div>
                    <div class="reveal rounded-[2rem] bg-white/5 p-6">
                        <p class="micro-label text-[#d9b87d]">Phone</p>
                        <p class="mt-3 leading-8 text-white/78">{{ $contact['phone_display'] }}</p>
                    </div>
                    <div class="reveal rounded-[2rem] bg-white/5 p-6">
                        <p class="micro-label text-[#d9b87d]">Email</p>
                        <p class="mt-3 break-all leading-8 text-white/78">{{ $contact['email'] }}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
