<section id="contact" class="section-pad">
    <div class="section-wrap contact-grid">
        <div class="contact-card reveal">
            <p class="eyebrow">Contact</p>
            <h2 class="section-title">Visit, ask, or book with confidence.</h2>
            <p class="section-copy">Reach out if you want help choosing the right room, planning a team setup, or confirming availability before you submit.</p>
        </div>

        <div class="contact-card reveal">
            <p class="mini-title">Call or Text</p>
            <p><a href="tel:{{ $contact['phone_href'] }}">{{ $contact['phone_display'] }}</a></p>
            <p class="mini-title">Email</p>
            <p><a href="mailto:{{ $contact['email'] }}">{{ $contact['email'] }}</a></p>
        </div>

        <div class="contact-card reveal">
            <p class="mini-title">Address</p>
            @foreach ($contact['address_lines'] as $line)
                <p>{{ $line }}</p>
            @endforeach
            <p class="mini-title">Hours</p>
            @foreach ($contact['hours_lines'] as $line)
                <p>{{ $line }}</p>
            @endforeach
            <p class="contact-link"><a href="{{ $contact['map_url'] }}" target="_blank" rel="noreferrer">Open in Maps</a></p>
        </div>
    </div>
</section>
