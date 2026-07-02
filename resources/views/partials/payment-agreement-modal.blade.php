@once
    <style>
        .agreement-link-button {
            border: 0;
            background: transparent;
            padding: 0;
            color: #2f6e42;
            font-weight: 700;
            text-decoration: underline;
            cursor: pointer;
        }

        .agreement-consent {
            margin-top: 0.85rem;
            border: 1px solid #dfe7d8;
            border-radius: 1rem;
            background: #fbfcf8;
            padding: 0.75rem 0.9rem;
        }

        .agreement-consent label {
            display: flex;
            align-items: flex-start;
            gap: 0.6rem;
            color: #30433a;
            font-size: 0.78rem;
            line-height: 1.45;
        }

        .agreement-consent input[type="checkbox"] {
            margin-top: 0.15rem;
            width: 0.95rem;
            height: 0.95rem;
            min-width: 0.95rem;
            accent-color: #44793b;
        }

        .agreement-modal {
            position: fixed;
            inset: 0;
            z-index: 10040;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
        }

        .agreement-modal.hidden {
            display: none;
        }

        .agreement-modal__backdrop {
            position: absolute;
            inset: 0;
            background: rgba(14, 24, 20, 0.38);
            backdrop-filter: blur(7px);
        }

        .agreement-modal__dialog {
            position: relative;
            width: min(100%, 760px);
            max-height: min(86vh, 900px);
            overflow: hidden;
            border: 1px solid #dfe7d8;
            border-radius: 1.6rem;
            background: #fffdf8;
            box-shadow: 0 28px 70px rgba(12, 24, 20, 0.18);
        }

        .agreement-modal__header,
        .agreement-modal__footer {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            padding: 1.2rem 1.35rem;
            border-bottom: 1px solid #edf1ea;
        }

        .agreement-modal__footer {
            border-top: 1px solid #edf1ea;
            border-bottom: 0;
        }

        .agreement-modal__header h2 {
            margin: 0;
            color: #173029;
            font-size: 1.15rem;
            font-weight: 800;
        }

        .agreement-modal__close {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 2.5rem;
            height: 2.5rem;
            border: 1px solid #dfe7d8;
            border-radius: 999px;
            background: #fff;
            color: #516257;
            font-size: 1.15rem;
            cursor: pointer;
        }

        .agreement-modal__body {
            max-height: calc(86vh - 150px);
            overflow-y: auto;
            padding: 1.25rem 1.35rem 1.4rem;
            color: #405148;
            line-height: 1.7;
        }

        .agreement-modal__body h3 {
            margin: 1rem 0 0.45rem;
            color: #173029;
            font-size: 0.95rem;
            font-weight: 800;
        }

        .agreement-modal__body p,
        .agreement-modal__body li {
            font-size: 0.88rem;
        }

        .agreement-modal__body ul {
            margin: 0.5rem 0 0;
            padding-left: 1.2rem;
        }

        .agreement-modal__footer button {
            border-radius: 0.95rem;
            border: 1px solid #44793b;
            background: #44793b;
            padding: 0.8rem 1.15rem;
            color: #fff;
            font-size: 0.84rem;
            font-weight: 700;
            cursor: pointer;
        }
    </style>
@endonce

@php
    $agreementModalId = $modalId ?? 'payment-agreement-modal';
@endphp

<div class="agreement-modal hidden" id="{{ $agreementModalId }}" data-agreement-modal>
    <div class="agreement-modal__backdrop" data-agreement-close></div>
    <div class="agreement-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="{{ $agreementModalId }}-title">
        <div class="agreement-modal__header">
            <div>
                <p class="text-[0.68rem] font-bold uppercase tracking-[0.2em] text-[#b39a5a]">HYVE policy</p>
                <h2 id="{{ $agreementModalId }}-title">Rules & Agreement</h2>
            </div>
            <button type="button" class="agreement-modal__close" data-agreement-close aria-label="Close rules and agreement">&times;</button>
        </div>

        <div class="agreement-modal__body">
            <p>Please review the official HYVE House Rules before submitting your booking or payment.</p>

            <h3>Professional Conduct</h3>
            <ul>
                <li>Respect all members, guests, and staff.</li>
                <li>Harassment, discrimination, and disruptive behavior are not allowed.</li>
                <li>Keep conversations professional.</li>
            </ul>

            <h3>Workplace Etiquette</h3>
            <ul>
                <li>Keep noise low in open areas.</li>
                <li>Use designated booths for calls.</li>
                <li>Only use seats or tables you need.</li>
                <li>Maintain a clean workstation.</li>
            </ul>

            <h3>Shared Spaces</h3>
            <ul>
                <li>Keep common areas tidy and considerate for all.</li>
                <li>Clean up after yourself and return items to their proper places.</li>
            </ul>

            <h3>Food & Beverage</h3>
            <ul>
                <li>Light food and drinks are allowed unless stated otherwise.</li>
                <li>Avoid strong smells in shared areas.</li>
                <li>Dispose of trash properly.</li>
            </ul>

            <h3>Internet & Equipment Use</h3>
            <ul>
                <li>Wi-Fi is for work use only.</li>
                <li>No illegal downloads or misuse allowed.</li>
                <li>Handle HYVE equipment carefully and report issues immediately.</li>
            </ul>

            <h3>Guest & Visitors</h3>
            <ul>
                <li>All guests must register at reception.</li>
                <li>Guests are only allowed in designated areas.</li>
                <li>Members are responsible for their guests' behavior.</li>
            </ul>

            <h3>Booking Use & Time Limits</h3>
            <ul>
                <li>Reserve meeting rooms in advance.</li>
                <li>Adhere to time limits during peak hours.</li>
                <li>Vacate promptly at the end of your booking.</li>
            </ul>

            <h3>Privacy & Security</h3>
            <ul>
                <li>Respect the privacy of others. No unauthorized photos or recordings.</li>
                <li>Do not access or use another member's belongings.</li>
                <li>HYVE is not responsible for unattended personal items.</li>
            </ul>

            <h3>Health & Safety</h3>
            <ul>
                <li>Keep walkways and emergency routes clear.</li>
                <li>Report spills and hazards immediately.</li>
                <li>Follow HYVE staff emergency instructions.</li>
            </ul>

            <h3>Prohibited Items & Activities</h3>
            <ul>
                <li>Smoking, vaping, alcohol, and illegal substances are strictly prohibited.</li>
                <li>Any illegal or unsafe activity will result in immediate removal.</li>
            </ul>

            <h3>Cleanliness Policy</h3>
            <ul>
                <li>Follow a clean desk policy.</li>
                <li>Avoid leaving personal items overnight unless permitted.</li>
                <li>Maintain hygiene in shared areas.</li>
            </ul>

            <h3>Damages & Liability</h3>
            <ul>
                <li>Members are liable for damage to HYVE property.</li>
                <li>Theft or intentional damage may lead to membership termination and legal action.</li>
            </ul>

            <h3>Violation of Rules</h3>
            <ul>
                <li>Verbal and written warnings</li>
                <li>Temporary suspension of access</li>
                <li>Termination of membership without refund</li>
            </ul>

            <h3>Agreement</h3>
            <p>By checking the agreement box, you confirm that you have read, understood, and agreed to follow the official HYVE House Rules during your booking and while using the workspace.</p>
        </div>

        <div class="agreement-modal__footer">
            <p class="text-[0.8rem] text-[#728075]">These house rules apply to members, guests, and all workspace users inside HYVE.</p>
            <button type="button" data-agreement-accept>I understand and agree</button>
        </div>
    </div>
</div>
