@php
    $hyveChatContact = config('hyve.contact', []);
    $hyveChatPhoneDisplay = $hyveChatContact['phone_display'] ?? '09706240749';
    $hyveChatPhoneHref = $hyveChatContact['phone_href'] ?? $hyveChatPhoneDisplay;
    $hyveChatEmail = $hyveChatContact['email'] ?? 'frontdesk@hyvecoworkingspace.com';
@endphp

<aside
    class="hyve-faq-chat"
    data-hyve-faq-chat
    data-support-create-url="{{ route('support.conversations.store') }}"
    data-csrf-token="{{ csrf_token() }}"
>
    <section class="hyve-faq-chat__panel" id="hyve-faq-chat-panel" data-hyve-faq-panel hidden aria-label="HYVE help">
        <header class="hyve-faq-chat__header">
            <div class="hyve-faq-chat__brand" aria-hidden="true">H</div>
            <div>
                <strong>HYVE Help</strong>
                <span>Quick answers &middot; Available 24/7</span>
            </div>
            <button type="button" class="hyve-faq-chat__close" data-hyve-faq-close aria-label="Close HYVE Help">&times;</button>
        </header>

        <div class="hyve-faq-chat__body" data-hyve-faq-view>
            <div class="hyve-faq-chat__message">
                <strong>Hi! How can we help?</strong>
                <p>Select a question below to get verified HYVE information right away.</p>
            </div>

            <div class="hyve-faq-chat__answer" data-hyve-faq-answer aria-live="polite">
                <p>Choose a topic to see the answer here.</p>
            </div>

            <div class="hyve-faq-chat__topics" aria-label="Frequently asked questions">
                <button type="button" data-hyve-faq-topic="booking">How do I book?</button>
                <button type="button" data-hyve-faq-topic="rates">Where can I see the rates?</button>
                <button type="button" data-hyve-faq-topic="hours">What are your operating hours?</button>
                <button type="button" data-hyve-faq-topic="walk-in">Do you accept walk-ins?</button>
                <button type="button" data-hyve-faq-topic="payments">What payment methods are available?</button>
                <button type="button" data-hyve-faq-topic="discounts">What discounts are available?</button>
                <button type="button" data-hyve-faq-topic="location">Where is HYVE located?</button>
            </div>

            <button type="button" class="hyve-faq-chat__staff-button" data-hyve-support-open>
                Message the HYVE Front Desk
                <span>Start a real conversation with our staff</span>
            </button>
        </div>

        <div class="hyve-faq-chat__body" data-hyve-support-view hidden>
            <button type="button" class="hyve-faq-chat__back" data-hyve-support-back>&larr; Back to quick answers</button>

            <div class="hyve-support-chat__intro">
                <strong>Chat with HYVE Front Desk</strong>
                <p>Send your question here. Staff replies will appear in this conversation automatically.</p>
            </div>

            <form class="hyve-support-chat__start" data-hyve-support-start-form>
                <label>
                    <span>Your name</span>
                    <input type="text" name="customer_name" maxlength="120" autocomplete="name" required>
                </label>
                <div class="hyve-support-chat__contact-grid">
                    <label>
                        <span>Email</span>
                        <input type="email" name="email" maxlength="190" autocomplete="email" placeholder="Email or phone is required">
                    </label>
                    <label>
                        <span>Phone</span>
                        <input type="tel" name="phone" maxlength="40" autocomplete="tel" placeholder="Email or phone is required">
                    </label>
                </div>
                <label>
                    <span>Your question</span>
                    <textarea name="message" rows="3" maxlength="2000" required placeholder="How can HYVE help you?"></textarea>
                </label>
                <button type="submit">Start conversation</button>
                <p class="hyve-support-chat__feedback" data-hyve-support-start-feedback></p>
            </form>

            <div class="hyve-support-chat__conversation" data-hyve-support-conversation hidden>
                <div class="hyve-support-chat__status"><span data-hyve-support-status>Open</span> conversation</div>
                <div class="hyve-support-chat__messages" data-hyve-support-messages aria-live="polite"></div>
                <form class="hyve-support-chat__reply" data-hyve-support-reply-form>
                    <textarea name="message" rows="2" maxlength="2000" required placeholder="Type a message..."></textarea>
                    <button type="submit" aria-label="Send message">Send</button>
                </form>
                <p class="hyve-support-chat__feedback" data-hyve-support-reply-feedback></p>
                <div class="hyve-support-chat__controls">
                    <button type="button" data-hyve-support-new>Start New Conversation</button>
                    <button type="button" data-hyve-support-forget>Forget This Conversation</button>
                </div>
            </div>
        </div>

        <footer class="hyve-faq-chat__footer">
            <a href="{{ route('bookings.index') }}" class="hyve-faq-chat__primary">Book Now</a>
            <a href="tel:{{ $hyveChatPhoneHref }}">Call HYVE</a>
        </footer>

        <template data-hyve-faq-template="booking"><strong>How to book</strong><p>Open the booking page, choose a room, select an available date and time, then complete your customer and payment details.</p><a href="{{ route('bookings.index') }}">Check availability and book &rarr;</a></template>
        <template data-hyve-faq-template="rates"><strong>HYVE rates</strong><p>Rates depend on the selected space, schedule, and stay period. The booking page calculates the live total before checkout.</p><a href="{{ route('home') }}#rates">View rates &rarr;</a></template>
        <template data-hyve-faq-template="hours"><strong>Open 24 hours</strong><p>HYVE is open Monday to Sunday, 24 hours. Available booking times still depend on existing room reservations.</p></template>
        <template data-hyve-faq-template="walk-in"><strong>Walk-ins are welcome</strong><p>Yes. Visit HYVE and the front-desk staff can check availability and create your walk-in booking.</p><a href="tel:{{ $hyveChatPhoneHref }}">Call {{ $hyveChatPhoneDisplay }} &rarr;</a></template>
        <template data-hyve-faq-template="payments"><strong>Payment options</strong><p>Available methods may include GCash, bank transfer, and cash for eligible walk-in transactions. The valid options and instructions appear during checkout.</p></template>
        <template data-hyve-faq-template="discounts"><strong>HYVE discounts</strong><p>Available offers include the 2-hour Common Area voucher, engagement, board reviewee, Early Bird, Senior, and PWD discounts. Eligibility and supporting ID or reference may be required.</p><a href="mailto:{{ $hyveChatEmail }}">Ask HYVE about eligibility &rarr;</a></template>
        <template data-hyve-faq-template="location"><strong>HYVE location</strong><p>10F The Space Building, A.S. Fortuna Street, Mandaue City.</p><a href="{{ $hyveChatContact['map_url'] ?? route('home').'#contact' }}" target="_blank" rel="noopener noreferrer">Open directions &rarr;</a></template>
    </section>

    <button type="button" class="hyve-faq-chat__launcher" data-hyve-faq-toggle aria-controls="hyve-faq-chat-panel" aria-expanded="false">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 4h16a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H9l-5 4v-4a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2Zm3 5a1 1 0 1 0 0 2 1 1 0 0 0 0-2Zm5 0a1 1 0 1 0 0 2 1 1 0 0 0 0-2Zm5 0a1 1 0 1 0 0 2 1 1 0 0 0 0-2Z" fill="currentColor"/></svg>
        <span>Ask HYVE</span>
    </button>
</aside>
