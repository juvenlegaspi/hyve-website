<section id="contact" class="section-pad">
    <div class="section-wrap">
        <div class="contact-section-heading reveal">
            <p class="eyebrow">Contact</p>
            <h2 class="section-title">Visit, ask, or book with confidence.</h2>
            <p class="section-copy">Reach out if you want help choosing the right room, planning a team setup, or confirming availability before you submit.</p>
        </div>

        <div class="contact-layout">
            <article class="contact-card contact-details-card reveal">
                <div class="contact-detail-item">
                    <span class="contact-detail-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24"><path d="M6.6 10.8a15.5 15.5 0 0 0 6.6 6.6l2.2-2.2a1 1 0 0 1 1-.24c1.1.37 2.26.56 3.43.56a1 1 0 0 1 1 1V20a1 1 0 0 1-1 1C10.5 21 3 13.5 3 4.17a1 1 0 0 1 1-1h3.5a1 1 0 0 1 1 1c0 1.17.19 2.33.56 3.43a1 1 0 0 1-.25 1Z" fill="currentColor"/></svg>
                    </span>
                    <div>
                        <p class="mini-title">Call or Text</p>
                        <a class="contact-detail-link" href="tel:{{ $contact['phone_href'] }}">{{ $contact['phone_display'] }}</a>
                        <p class="contact-detail-note">Tap the number to call HYVE directly.</p>
                    </div>
                </div>

                <div class="contact-detail-item">
                    <span class="contact-detail-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24"><path d="M3 5h18a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1H3a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1Zm9 7 7.5-5H4.5L12 12Zm0 2.4L4 9.07V17h16V9.07Z" fill="currentColor"/></svg>
                    </span>
                    <div>
                        <p class="mini-title">Email</p>
                        <a class="contact-detail-link contact-detail-link--email" href="mailto:{{ $contact['email'] }}">{{ $contact['email'] }}</a>
                        <p class="contact-detail-note">Send your questions or booking concerns anytime.</p>
                    </div>
                </div>

                <div class="contact-details-meta">
                    <div>
                        <p class="mini-title">Address</p>
                        @foreach ($contact['address_lines'] as $line)
                            <p>{{ $line }}</p>
                        @endforeach
                    </div>
                    <div>
                        <p class="mini-title">Hours</p>
                        @foreach ($contact['hours_lines'] as $line)
                            <p>{{ $line }}</p>
                        @endforeach
                    </div>
                </div>
            </article>

            <article class="contact-card contact-location-card reveal">
                <div class="contact-location-map">
                    <iframe
                        src="{{ $contact['map_embed_url'] }}"
                        title="HYVE Workspace location map"
                        loading="lazy"
                        referrerpolicy="no-referrer-when-downgrade"
                        allowfullscreen
                    ></iframe>
                </div>

                <div class="contact-location-footer">
                    <div>
                        <p class="mini-title">Find HYVE</p>
                        <h3>The Space Building, Mandaue City</h3>
                        <p>A.S. Fortuna Street · Open Monday to Sunday, 24 hours</p>
                    </div>

                    <a
                        href="{{ $contact['map_url'] }}"
                        class="button button--dark contact-map-button"
                        target="_blank"
                        rel="noopener noreferrer"
                        aria-label="Open the HYVE location in Google Maps"
                    >
                        <svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M12 2a7 7 0 0 0-7 7c0 5.25 7 13 7 13s7-7.75 7-13a7 7 0 0 0-7-7Zm0 9.5A2.5 2.5 0 1 1 12 6a2.5 2.5 0 0 1 0 5.5Z" fill="currentColor"/>
                        </svg>
                        Get directions
                    </a>
                </div>
            </article>
        </div>
    </div>
</section>
