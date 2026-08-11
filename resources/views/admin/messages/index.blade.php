@extends('layouts.admin')

@section('content')
    <div
        class="mx-auto max-w-[92rem]"
        data-admin-support-messages
        data-feed-url="{{ route('admin.messages.feed') }}"
        data-csrf-token="{{ csrf_token() }}"
    >
        <div class="mb-5 flex flex-wrap items-end justify-between gap-3">
            <div>
                <p class="text-[0.72rem] font-bold uppercase tracking-[0.18em] text-[#b48a3d]">Customer support</p>
                <h1 class="mt-1 text-[2rem] font-semibold tracking-[-0.04em] text-[#102e25]">Messages</h1>
                <p class="mt-1 text-[0.82rem] text-[#7c8279]">Reply to website visitors and manage active support conversations.</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <button type="button" class="rounded-full border border-[#d9e3d4] bg-white px-4 py-2 text-[0.72rem] font-semibold text-[#486152]" data-admin-support-enable-notifications>Enable alerts</button>
                <div class="rounded-full border border-[#dce6d4] bg-white px-4 py-2 text-[0.75rem] font-semibold text-[#4c6455]">
                    <span data-admin-support-unread>0</span> unread
                </div>
            </div>
        </div>

        <div class="grid overflow-hidden rounded-[1.3rem] border border-[#dfe7d8] bg-white shadow-[0_1rem_3rem_rgba(25,54,44,0.07)] lg:h-[calc(100dvh-11rem)] lg:min-h-[34rem] lg:grid-cols-[22rem_minmax(0,1fr)]">
            <aside class="flex min-h-0 flex-col overflow-hidden border-b border-[#e6ece1] lg:border-b-0 lg:border-r">
                <div class="shrink-0 border-b border-[#edf1e9] px-4 py-3">
                    <strong class="text-[0.82rem] text-[#18372d]">Conversations</strong>
                    <p class="mt-0.5 text-[0.68rem] text-[#92998e]">Newest activity appears first.</p>
                    <div class="mt-3 grid gap-2" data-admin-support-filters>
                        <input type="search" name="search" maxlength="120" class="w-full rounded-[0.7rem] border border-[#dce4d7] px-3 py-2 text-[0.7rem] outline-none focus:border-[#6f9c58]" placeholder="Search name, email, or phone">
                        <div class="grid grid-cols-[1fr_auto] gap-2">
                            <select name="status" class="rounded-[0.7rem] border border-[#dce4d7] px-2.5 py-2 text-[0.68rem] outline-none">
                                <option value="all">All conversations</option>
                                <option value="open">Open</option>
                                <option value="closed">Closed</option>
                            </select>
                            <label class="flex cursor-pointer items-center gap-1.5 rounded-[0.7rem] border border-[#dce4d7] px-2.5 text-[0.65rem] font-semibold text-[#586b60]">
                                <input type="checkbox" name="unread" value="1" class="accent-[#397a40]">
                                Unread
                            </label>
                        </div>
                    </div>
                </div>
                <div class="min-h-0 max-h-[18rem] overflow-y-auto overscroll-contain lg:max-h-none lg:flex-1" data-admin-support-list>
                    <div class="p-5 text-center text-[0.76rem] text-[#8a9187]">Loading conversations…</div>
                </div>
            </aside>

            <section class="flex h-[36rem] min-h-0 min-w-0 flex-col overflow-hidden lg:h-full" data-admin-support-thread>
                <div class="grid flex-1 place-items-center p-6 text-center text-[#8a9187]" data-admin-support-empty>
                    <div>
                        <strong class="block text-[0.95rem] text-[#4a5f55]">No conversation selected</strong>
                        <span class="mt-1 block text-[0.76rem]">Select a customer message from the inbox.</span>
                    </div>
                </div>

                <div class="hidden h-full min-h-0 flex-1 flex-col overflow-hidden" data-admin-support-active>
                    <header class="flex shrink-0 flex-wrap items-center justify-between gap-3 border-b border-[#e8ede4] px-4 py-3.5 sm:px-5">
                        <div>
                            <strong class="block text-[0.9rem] text-[#14342a]" data-admin-support-name>Customer</strong>
                            <span class="mt-0.5 block text-[0.68rem] text-[#899186]" data-admin-support-contact></span>
                            <span class="mt-1 inline-flex rounded-full bg-[#edf4e7] px-2 py-0.5 text-[0.61rem] font-bold text-[#47704b]" data-admin-support-mode></span>
                            <a href="#" class="mt-1 hidden text-[0.65rem] font-semibold text-[#397a40] underline underline-offset-2" data-admin-support-booking-match></a>
                        </div>
                        <div class="flex items-center gap-2">
                            <button type="button" class="rounded-full border border-[#e8cfc8] px-3.5 py-2 text-[0.7rem] font-semibold text-[#a44838]" data-admin-support-delete>Delete</button>
                            <button type="button" class="rounded-full border border-[#d7e2d1] px-3.5 py-2 text-[0.7rem] font-semibold text-[#486050]" data-admin-support-status>Close conversation</button>
                        </div>
                    </header>

                    <div class="flex min-h-0 flex-1 flex-col gap-3 overflow-y-auto overscroll-contain bg-[#fafbf7] p-4 sm:p-5" data-admin-support-messages></div>

                    <form class="shrink-0 border-t border-[#e5ebe0] bg-white p-3 sm:p-4" data-admin-support-reply-form>
                        <div class="mb-2 hidden items-center justify-between gap-3 rounded-[0.75rem] border-l-4 border-[#4c8750] bg-[#f3f8ed] px-3 py-2 text-[0.67rem] text-[#52665a]" data-admin-support-replying>
                            <span class="min-w-0"><strong class="block" data-admin-support-replying-name></strong><small class="block truncate" data-admin-support-replying-body></small></span>
                            <button type="button" class="text-lg leading-none" data-admin-support-reply-cancel aria-label="Cancel reply">&times;</button>
                        </div>
                        <div class="mb-2 flex flex-wrap gap-1.5" data-admin-support-quick-replies>
                            <button type="button" class="rounded-full border border-[#dce5d7] px-2.5 py-1 text-[0.61rem] font-semibold text-[#5c7064]" data-quick-reply="Thank you for contacting HYVE. How can we assist you further?">Thank customer</button>
                            <button type="button" class="rounded-full border border-[#dce5d7] px-2.5 py-1 text-[0.61rem] font-semibold text-[#5c7064]" data-quick-reply="May we know your preferred date, start time, and room?">Ask schedule</button>
                            <button type="button" class="rounded-full border border-[#dce5d7] px-2.5 py-1 text-[0.61rem] font-semibold text-[#5c7064]" data-quick-reply="We are checking this with the HYVE Front Desk and will update you shortly.">Checking</button>
                            <button type="button" class="rounded-full border border-[#cfe0c7] bg-[#f3f8ed] px-2.5 py-1 text-[0.61rem] font-semibold text-[#39713d]" data-admin-support-send-booking>Send Book Now</button>
                        </div>
                        <div class="flex items-end gap-2">
                            <details class="admin-support-emoji-picker" data-admin-support-emoji-picker>
                                <summary aria-label="Choose emoji">&#9786;</summary>
                                <div class="admin-support-emoji-picker__menu">
                                    @foreach (['😀', '😊', '😂', '😍', '👍', '❤️', '🙏', '🎉'] as $emoji)
                                        <button type="button" data-admin-support-insert-emoji="{{ $emoji }}">{{ $emoji }}</button>
                                    @endforeach
                                </div>
                            </details>
                            <textarea name="message" rows="2" maxlength="2000" required class="min-h-[3rem] flex-1 resize-none rounded-[0.9rem] border border-[#d8e1d3] px-3.5 py-2.5 text-[0.78rem] outline-none focus:border-[#6d9c53]" placeholder="Type your reply... (Enter to send, Shift+Enter for a new line)"></textarea>
                            <button type="submit" class="min-h-[3rem] rounded-[0.9rem] bg-[#34753d] px-5 text-[0.75rem] font-bold text-white disabled:opacity-60">Send reply</button>
                        </div>
                        <p class="mt-1.5 text-[0.64rem] text-[#93998f]" data-admin-support-feedback>Replies appear automatically in the customer’s HYVE chat widget.</p>
                    </form>
                </div>
            </section>
        </div>
    </div>
@endsection
