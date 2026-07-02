@extends('layouts.admin')

@section('content')
    @php
        $setting = $paymentSetting;
        $downpaymentPercentage = (float) ($setting?->downpayment_percentage ?? 50);
        $mikrotikPort = (int) ($setting?->mikrotik_port ?? 8728);
        $hasMikrotikSetup = $setting?->hasMikrotikSetup() ?? false;
        $websiteQrImage = 'https://quickchart.io/qr?size=320&margin=2&dark=173029&light=ffffff&text='.rawurlencode($websiteUrl);
    @endphp

    <style>
        .admin-settings-shell {
            display: grid;
            gap: 1.25rem;
        }

        .admin-settings-card {
            border: 1px solid #dfe7d8;
            border-radius: 1.25rem;
            background: #fff;
            box-shadow: 0 18px 42px rgba(17, 28, 24, 0.05);
        }

        .admin-settings-stat {
            border: 1px solid #e4eadf;
            border-radius: 1rem;
            background: #fbfcf8;
            padding: 1rem;
        }

        .admin-settings-stat__label {
            color: #9b9689;
            font-size: 0.78rem;
        }

        .admin-settings-stat__value {
            margin-top: 0.45rem;
            color: #132320;
            font-size: 1.45rem;
            font-weight: 700;
            line-height: 1.1;
        }

        .admin-settings-stat__note {
            margin-top: 0.45rem;
            color: #6f786d;
            font-size: 0.76rem;
            line-height: 1.5;
        }

        .admin-settings-grid {
            display: grid;
            gap: 1rem;
        }

        .admin-settings-form {
            display: grid;
            gap: 1rem;
        }

        .admin-settings-form__section {
            display: grid;
            gap: 0.9rem;
            padding: 1rem;
            border: 1px solid #e7ece2;
            border-radius: 1rem;
            background: #fcfdf9;
        }

        .admin-settings-form__section h3 {
            margin: 0;
            color: #173029;
            font-size: 0.95rem;
            font-weight: 700;
        }

        .admin-settings-form__section p {
            margin: 0.15rem 0 0;
            color: #7f877d;
            font-size: 0.78rem;
            line-height: 1.5;
        }

        .admin-settings-form__fields {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 0.85rem;
        }

        .admin-settings-form__field {
            display: grid;
            gap: 0.35rem;
        }

        .admin-settings-form__field--full {
            grid-column: 1 / -1;
        }

        .admin-settings-form__field label {
            color: #8f897d;
            font-size: 0.72rem;
            font-weight: 800;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .admin-settings-form__field input,
        .admin-settings-form__field textarea {
            width: 100%;
            border: 1px solid #dfe7d8;
            border-radius: 0.88rem;
            background: #fff;
            padding: 0.82rem 0.95rem;
            color: #173029;
            font-size: 0.82rem;
        }

        .admin-settings-form__field textarea {
            min-height: 7.5rem;
            resize: vertical;
        }

        .admin-settings-form__actions {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 0.85rem;
        }

        .admin-settings-form__hint {
            color: #7f877d;
            font-size: 0.76rem;
            line-height: 1.5;
        }

        .admin-settings-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 0.9rem;
            border: 1px solid #44793b;
            background: #44793b;
            padding: 0.85rem 1.2rem;
            color: #fff;
            font-size: 0.82rem;
            font-weight: 700;
        }

        .admin-settings-preview {
            display: grid;
            gap: 0.9rem;
        }

        .admin-settings-preview__box {
            border: 1px solid #e7ece2;
            border-radius: 1rem;
            background: #fcfdf9;
            padding: 1rem;
        }

        .admin-settings-preview__title {
            color: #173029;
            font-size: 0.9rem;
            font-weight: 700;
        }

        .admin-settings-preview__copy {
            margin-top: 0.45rem;
            color: #667267;
            font-size: 0.8rem;
            line-height: 1.6;
        }

        .admin-settings-qr-preview {
            width: min(100%, 220px);
            border: 1px solid #dfe7d8;
            border-radius: 1rem;
            background: #fff;
            padding: 0.75rem;
        }

        .admin-settings-qr-preview img {
            display: block;
            width: 100%;
            height: auto;
            border-radius: 0.8rem;
        }

        .admin-settings-link {
            color: #1e4a35;
            font-size: 0.8rem;
            font-weight: 700;
            word-break: break-all;
        }

        .admin-settings-button--ghost {
            background: #fff;
            color: #44793b;
            border-color: #cfe0c9;
        }

        @media (max-width: 900px) {
            .admin-settings-form__fields {
                grid-template-columns: minmax(0, 1fr);
            }
        }
    </style>

    <div class="admin-settings-shell">
        <div>
            <p class="text-[0.68rem] font-bold uppercase tracking-[0.22em] text-[#b39a5a]">System setup</p>
            <h1 class="mt-2 text-[1.65rem] font-semibold tracking-[-0.05em] text-[#132320]">Settings</h1>
            <p class="mt-1 text-[0.84rem] text-[#8b897f]">Control payment instructions and booking rules used by the public booking flow.</p>
        </div>

        <section class="grid gap-3.5 md:grid-cols-2 xl:grid-cols-4">
            <article class="admin-settings-stat">
                <p class="admin-settings-stat__label">GCash account</p>
                <strong class="admin-settings-stat__value">{{ $setting?->gcash_account_name ?: 'Not set' }}</strong>
                <p class="admin-settings-stat__note">{{ $setting?->gcash_number ?: 'No GCash number saved yet.' }}</p>
                <p class="admin-settings-stat__note">{{ $setting?->gcash_qr_path ? 'QR code uploaded and ready for scan.' : 'No GCash QR uploaded yet.' }}</p>
            </article>
            <article class="admin-settings-stat">
                <p class="admin-settings-stat__label">Bank transfer</p>
                <strong class="admin-settings-stat__value">{{ $setting?->bank_name ?: 'Not set' }}</strong>
                <p class="admin-settings-stat__note">{{ $setting?->bank_account_number ?: 'No bank account saved yet.' }}</p>
                <p class="admin-settings-stat__note">{{ $setting?->bank_qr_path ? 'Bank QR uploaded and ready for scan.' : 'No bank QR uploaded yet.' }}</p>
            </article>
            <article class="admin-settings-stat">
                <p class="admin-settings-stat__label">Downpayment rule</p>
                <strong class="admin-settings-stat__value">{{ number_format($downpaymentPercentage, 0) }}%</strong>
                <p class="admin-settings-stat__note">Used by booking quote and checkout calculations.</p>
            </article>
            <article class="admin-settings-stat">
                <p class="admin-settings-stat__label">Active profile</p>
                <strong class="admin-settings-stat__value">{{ $setting?->is_active ? 'Live' : 'Draft' }}</strong>
                <p class="admin-settings-stat__note">The booking page reads the active payment setting record.</p>
            </article>
            <article class="admin-settings-stat">
                <p class="admin-settings-stat__label">MikroTik router</p>
                <strong class="admin-settings-stat__value">{{ $setting?->mikrotik_enabled ? 'Enabled' : 'Disabled' }}</strong>
                <p class="admin-settings-stat__note">{{ $hasMikrotikSetup ? (($setting?->mikrotik_host ?: 'Router').' : '.$mikrotikPort) : 'Router settings are not complete yet.' }}</p>
            </article>
        </section>

        <section class="grid gap-4 xl:grid-cols-[1.08fr_0.92fr]">
            <article class="admin-settings-card p-5">
                <form method="POST" action="{{ route('admin.settings.update') }}" enctype="multipart/form-data" class="admin-settings-form">
                    @csrf
                    @method('PATCH')

                    <div class="admin-settings-form__section">
                        <div>
                            <h3>Payment instructions</h3>
                            <p>These details are shown to customers during booking and balance payment submission.</p>
                        </div>

                        <div class="admin-settings-form__fields">
                            <div class="admin-settings-form__field">
                                <label for="gcash_account_name">GCash account name</label>
                                <input id="gcash_account_name" type="text" name="gcash_account_name" value="{{ old('gcash_account_name', $setting?->gcash_account_name) }}" @disabled(! $canManageSettings)>
                            </div>

                            <div class="admin-settings-form__field">
                                <label for="gcash_number">GCash number</label>
                                <input id="gcash_number" type="text" name="gcash_number" value="{{ old('gcash_number', $setting?->gcash_number) }}" @disabled(! $canManageSettings)>
                            </div>

                            <div class="admin-settings-form__field admin-settings-form__field--full">
                                <label for="gcash_qr">GCash QR code</label>
                                <input id="gcash_qr" type="file" name="gcash_qr" accept="image/png,image/jpeg,image/webp" @disabled(! $canManageSettings)>
                                <p>Upload the QR image that customers can scan during checkout.</p>
                                @if ($setting?->gcash_qr_path)
                                    <div class="admin-settings-qr-preview">
                                        <img src="{{ url('storage/'.$setting->gcash_qr_path) }}" alt="GCash QR code preview">
                                    </div>
                                @endif
                            </div>

                            <div class="admin-settings-form__field">
                                <label for="bank_name">Bank name</label>
                                <input id="bank_name" type="text" name="bank_name" value="{{ old('bank_name', $setting?->bank_name) }}" @disabled(! $canManageSettings)>
                            </div>

                            <div class="admin-settings-form__field">
                                <label for="bank_account_name">Bank account name</label>
                                <input id="bank_account_name" type="text" name="bank_account_name" value="{{ old('bank_account_name', $setting?->bank_account_name) }}" @disabled(! $canManageSettings)>
                            </div>

                            <div class="admin-settings-form__field admin-settings-form__field--full">
                                <label for="bank_account_number">Bank account number</label>
                                <input id="bank_account_number" type="text" name="bank_account_number" value="{{ old('bank_account_number', $setting?->bank_account_number) }}" @disabled(! $canManageSettings)>
                            </div>

                            <div class="admin-settings-form__field admin-settings-form__field--full">
                                <label for="bank_qr">Bank transfer QR code</label>
                                <input id="bank_qr" type="file" name="bank_qr" accept="image/png,image/jpeg,image/webp" @disabled(! $canManageSettings)>
                                <p>Upload the bank QR image that customers can scan during checkout.</p>
                                @if ($setting?->bank_qr_path)
                                    <div class="admin-settings-qr-preview">
                                        <img src="{{ url('storage/'.$setting->bank_qr_path) }}" alt="Bank transfer QR code preview">
                                    </div>
                                @endif
                            </div>

                            <div class="admin-settings-form__field admin-settings-form__field--full">
                                <label for="instructions">Payment instructions message</label>
                                <textarea id="instructions" name="instructions" @disabled(! $canManageSettings)>{{ old('instructions', $setting?->instructions) }}</textarea>
                            </div>
                        </div>
                    </div>

                    <div class="admin-settings-form__section">
                        <div>
                            <h3>Booking rules</h3>
                            <p>This rule feeds the booking quote and minimum downpayment shown to customers.</p>
                        </div>

                        <div class="admin-settings-form__fields">
                            <div class="admin-settings-form__field">
                                <label for="downpayment_percentage">Required downpayment percentage</label>
                                <input id="downpayment_percentage" type="number" name="downpayment_percentage" min="1" max="100" step="0.01" value="{{ old('downpayment_percentage', $setting?->downpayment_percentage ?? 50) }}" @disabled(! $canManageSettings)>
                            </div>
                        </div>
                    </div>

                    <div class="admin-settings-form__section">
                        <div>
                            <h3>MikroTik WiFi setup</h3>
                            <p>Prepare the router connection details for future voucher sync. This will not connect to the device yet until the MikroTik is available.</p>
                        </div>

                        <div class="admin-settings-form__fields">
                            <div class="admin-settings-form__field admin-settings-form__field--full">
                                <label for="mikrotik_enabled">Enable MikroTik voucher sync</label>
                                <input id="mikrotik_enabled" type="hidden" name="mikrotik_enabled" value="0">
                                <input id="mikrotik_enabled" type="checkbox" name="mikrotik_enabled" value="1" @checked(old('mikrotik_enabled', $setting?->mikrotik_enabled)) @disabled(! $canManageSettings) style="width:1rem;height:1rem;accent-color:#44793b;">
                            </div>

                            <div class="admin-settings-form__field">
                                <label for="mikrotik_host">Router IP / host</label>
                                <input id="mikrotik_host" type="text" name="mikrotik_host" value="{{ old('mikrotik_host', $setting?->mikrotik_host) }}" placeholder="192.168.88.1" @disabled(! $canManageSettings)>
                            </div>

                            <div class="admin-settings-form__field">
                                <label for="mikrotik_port">API port</label>
                                <input id="mikrotik_port" type="number" name="mikrotik_port" min="1" max="65535" value="{{ old('mikrotik_port', $mikrotikPort) }}" placeholder="8728" @disabled(! $canManageSettings)>
                            </div>

                            <div class="admin-settings-form__field">
                                <label for="mikrotik_username">Router username</label>
                                <input id="mikrotik_username" type="text" name="mikrotik_username" value="{{ old('mikrotik_username', $setting?->mikrotik_username) }}" placeholder="admin" @disabled(! $canManageSettings)>
                            </div>

                            <div class="admin-settings-form__field">
                                <label for="mikrotik_password">Router password</label>
                                <input id="mikrotik_password" type="password" name="mikrotik_password" value="" placeholder="{{ $setting?->mikrotik_password ? 'Saved password kept unless replaced' : 'Enter router password' }}" @disabled(! $canManageSettings)>
                            </div>

                            <div class="admin-settings-form__field">
                                <label for="mikrotik_hotspot_server">Hotspot server name</label>
                                <input id="mikrotik_hotspot_server" type="text" name="mikrotik_hotspot_server" value="{{ old('mikrotik_hotspot_server', $setting?->mikrotik_hotspot_server) }}" placeholder="hotspot1" @disabled(! $canManageSettings)>
                            </div>

                            <div class="admin-settings-form__field">
                                <label for="mikrotik_dns_name">Hotspot DNS / portal name</label>
                                <input id="mikrotik_dns_name" type="text" name="mikrotik_dns_name" value="{{ old('mikrotik_dns_name', $setting?->mikrotik_dns_name) }}" placeholder="wifi.hyve.local" @disabled(! $canManageSettings)>
                            </div>
                        </div>
                    </div>

                    <div class="admin-settings-form__actions">
                        <p class="admin-settings-form__hint">
                            @if ($canManageSettings)
                                Changes here update the live values used by the booking checkout and member balance payment screens.
                            @else
                                Your role can view these settings but cannot edit them.
                            @endif
                        </p>

                        @if ($canManageSettings)
                            <div class="flex flex-wrap items-center gap-3">
                                <button type="submit" class="admin-settings-button">Save settings</button>
                            </div>
                        @endif
                    </div>
                </form>

                @if ($canManageSettings)
                    <form method="POST" action="{{ route('admin.settings.test-mikrotik') }}" class="mt-3">
                        @csrf
                        <button type="submit" class="admin-settings-button" style="background:#fff;color:#44793b;border-color:#cfe0c9;">Test MikroTik connection</button>
                    </form>
                @endif
            </article>

            <aside class="admin-settings-preview">
                <article class="admin-settings-card p-5">
                    <h2 class="text-[1rem] font-semibold text-[#132320]">Customer preview</h2>
                    <div class="mt-4 grid gap-3">
                        <div class="admin-settings-preview__box">
                            <div class="admin-settings-preview__title">GCash block</div>
                            <div class="admin-settings-preview__copy">
                                <strong>{{ $setting?->gcash_account_name ?: 'HYVE Workspace' }}</strong><br>
                                {{ $setting?->gcash_number ?: '0917 123 4567' }}
                            </div>
                        </div>

                        <div class="admin-settings-preview__box">
                            <div class="admin-settings-preview__title">Bank transfer block</div>
                            <div class="admin-settings-preview__copy">
                                <strong>{{ $setting?->bank_name ?: 'Bank Name' }}</strong><br>
                                {{ $setting?->bank_account_name ?: 'Account Name' }}<br>
                                {{ $setting?->bank_account_number ?: 'Account Number' }}
                            </div>
                        </div>

                        <div class="admin-settings-preview__box">
                            <div class="admin-settings-preview__title">Instruction message</div>
                            <div class="admin-settings-preview__copy">
                                {{ $setting?->instructions ?: 'Send the required downpayment first, then upload your proof for checking.' }}
                            </div>
                        </div>

                        <div class="admin-settings-preview__box">
                            <div class="admin-settings-preview__title">Website QR</div>
                            <div class="admin-settings-preview__copy">
                                Customers can scan this QR to open the HYVE website directly.
                            </div>
                            <div class="mt-3 admin-settings-qr-preview">
                                <img src="{{ $websiteQrImage }}" alt="HYVE website QR code">
                            </div>
                            <div class="mt-3">
                                <a href="{{ $websiteUrl }}" target="_blank" rel="noopener" class="admin-settings-link">{{ $websiteUrl }}</a>
                            </div>
                            <div class="mt-3 flex flex-wrap items-center gap-3">
                                <a href="{{ $websiteUrl }}" target="_blank" rel="noopener" class="admin-settings-button">Open website</a>
                                <button
                                    type="button"
                                    class="admin-settings-button admin-settings-button--ghost"
                                    onclick="printWebsiteQrPoster('{{ e($websiteUrl) }}', '{{ e($websiteQrImage) }}')"
                                >
                                    Print QR
                                </button>
                            </div>
                        </div>
                    </div>
                </article>

                <article class="admin-settings-card p-5">
                    <h2 class="text-[1rem] font-semibold text-[#132320]">Live behavior</h2>
                    <div class="mt-4 grid gap-3">
                        <div class="admin-settings-preview__box">
                            <div class="admin-settings-preview__title">Downpayment calculation</div>
                            <div class="admin-settings-preview__copy">
                                Customers are currently asked to follow a <strong>{{ number_format($downpaymentPercentage, 0) }}%</strong> downpayment rule during booking checkout.
                            </div>
                        </div>
                        <div class="admin-settings-preview__box">
                            <div class="admin-settings-preview__title">Where this appears</div>
                            <div class="admin-settings-preview__copy">
                                Booking page checkout, member balance payment page, and payment instruction blocks all read from this active setting profile.
                            </div>
                        </div>
                        <div class="admin-settings-preview__box">
                            <div class="admin-settings-preview__title">MikroTik voucher readiness</div>
                            <div class="admin-settings-preview__copy">
                                Status:
                                <strong>{{ $setting?->mikrotik_enabled ? 'Enabled' : 'Disabled' }}</strong><br>
                                Router: {{ $setting?->mikrotik_host ?: 'Not set' }}{{ $setting?->mikrotik_host ? ':'.$mikrotikPort : '' }}<br>
                                API user: {{ $setting?->mikrotik_username ?: 'Not set' }}<br>
                                Hotspot server: {{ $setting?->mikrotik_hotspot_server ?: 'Not set' }}<br>
                                {{ $hasMikrotikSetup ? 'HYVE is ready for real MikroTik voucher sync once the router is available.' : 'Complete the router fields now so the voucher module can connect faster later.' }}
                            </div>
                        </div>
                    </div>
                </article>
            </aside>
        </section>
    </div>

    <script>
        function printWebsiteQr(websiteUrl, qrImage) {
            const printWindow = window.open('', '_blank', 'width=900,height=900');

            if (!printWindow) {
                window.alert('Please allow pop-ups first so HYVE can print the website QR.');
                return;
            }

            printWindow.document.write(`
                <!doctype html>
                <html>
                <head>
                    <meta charset="utf-8">
                    <title>HYVE Website QR</title>
                    <style>
                        body {
                            margin: 0;
                            font-family: Calibri, Arial, sans-serif;
                            background: #f6f7f2;
                            color: #173029;
                        }
                        .sheet {
                            width: 720px;
                            margin: 32px auto;
                            background: #ffffff;
                            border: 1px solid #dfe7d8;
                            border-radius: 24px;
                            padding: 40px;
                            text-align: center;
                            box-sizing: border-box;
                        }
                        .eyebrow {
                            font-size: 12px;
                            font-weight: 700;
                            letter-spacing: 0.28em;
                            text-transform: uppercase;
                            color: #b39a5a;
                        }
                        .title {
                            margin: 16px 0 10px;
                            font-size: 38px;
                            font-weight: 700;
                            letter-spacing: 0.04em;
                        }
                        .copy {
                            margin: 0 auto 24px;
                            max-width: 520px;
                            font-size: 18px;
                            line-height: 1.6;
                            color: #5e6e60;
                        }
                        .qr-wrap {
                            width: 340px;
                            margin: 0 auto;
                            padding: 18px;
                            border: 1px solid #dfe7d8;
                            border-radius: 24px;
                            background: #fbfcf8;
                        }
                        .qr-wrap img {
                            display: block;
                            width: 100%;
                            height: auto;
                            border-radius: 18px;
                        }
                        .link {
                            margin-top: 24px;
                            font-size: 18px;
                            font-weight: 700;
                            color: #173029;
                            word-break: break-all;
                        }
                        .note {
                            margin-top: 14px;
                            font-size: 14px;
                            color: #7b847c;
                        }
                        @media print {
                            body {
                                background: #ffffff;
                            }
                            .sheet {
                                width: auto;
                                margin: 0;
                                border: 0;
                                border-radius: 0;
                                box-shadow: none;
                            }
                        }
                    </style>
                </head>
                <body>
                    <div class="sheet">
                        <div class="eyebrow">Scan To Book</div>
                        <div class="title">HYVE Website</div>
                        <p class="copy">Scan this QR code to open the HYVE Coworking Space website and proceed directly to the booking page.</p>
                        <div class="qr-wrap">
                            <img src="${qrImage}" alt="HYVE Website QR Code">
                        </div>
                        <div class="link">${websiteUrl}</div>
                        <div class="note">HYVE Coworking Space • Mandaue City</div>
                    </div>
                </body>
                </html>
            `);

            printWindow.document.close();
            printWindow.focus();
            printWindow.print();
        }

        function printWebsiteQrPoster(websiteUrl, qrImage) {
            const printWindow = window.open('', '_blank', 'width=960,height=960');

            if (!printWindow) {
                window.alert('Please allow pop-ups first so HYVE can print the website QR.');
                return;
            }

            const html = [
                '<!doctype html>',
                '<html>',
                '<head>',
                '<meta charset="utf-8">',
                '<title>HYVE Website QR</title>',
                '<style>',
                '*{box-sizing:border-box;}',
                'body{margin:0;font-family:"Segoe UI",Calibri,Arial,sans-serif;background:radial-gradient(circle at top left, rgba(201,180,120,0.18), transparent 28%), linear-gradient(180deg, #f8f7f1 0%, #eef3ea 100%);color:#173029;}',
                '.sheet{width:780px;margin:30px auto;overflow:hidden;background:#ffffff;border:1px solid #dfe7d8;border-radius:32px;box-shadow:0 30px 70px rgba(23,48,41,0.12);}',
                '.hero{padding:42px 42px 28px;background:linear-gradient(135deg, rgba(196,177,121,0.14), rgba(68,121,59,0.08)), #fcfcf7;border-bottom:1px solid #e4eadf;}',
                '.eyebrow{font-size:12px;font-weight:700;letter-spacing:0.28em;text-transform:uppercase;color:#b39a5a;}',
                '.title{margin:14px 0 10px;font-size:42px;font-weight:700;letter-spacing:0.02em;}',
                '.copy{margin:0;max-width:560px;font-size:18px;line-height:1.6;color:#5e6e60;}',
                '.content{display:grid;grid-template-columns:360px 1fr;gap:26px;align-items:center;padding:34px 42px 24px;}',
                '.qr-wrap{width:100%;padding:20px;border:1px solid #dfe7d8;border-radius:28px;background:linear-gradient(180deg, #ffffff 0%, #f8fbf5 100%);}',
                '.qr-wrap img{display:block;width:100%;height:auto;border-radius:22px;background:#ffffff;}',
                '.side-card{border:1px solid #e1e8dc;border-radius:28px;background:#fbfcf8;padding:26px 24px;}',
                '.side-label{font-size:11px;font-weight:800;letter-spacing:0.2em;text-transform:uppercase;color:#9e915f;}',
                '.side-title{margin:12px 0 10px;font-size:28px;font-weight:700;line-height:1.15;color:#173029;}',
                '.side-copy{margin:0;font-size:16px;line-height:1.7;color:#667267;}',
                '.steps{margin:22px 0 0;padding:0;list-style:none;display:grid;gap:12px;}',
                '.step{display:flex;gap:12px;align-items:flex-start;}',
                '.step-badge{flex:0 0 34px;width:34px;height:34px;border-radius:999px;background:#173029;color:#ffffff;font-size:14px;font-weight:700;display:inline-flex;align-items:center;justify-content:center;}',
                '.step-copy{font-size:14px;line-height:1.6;color:#56655b;padding-top:4px;}',
                '.link{margin-top:18px;font-size:17px;font-weight:700;color:#173029;word-break:break-all;}',
                '.footer{display:flex;justify-content:space-between;gap:18px;align-items:center;padding:0 42px 34px;color:#718074;font-size:13px;}',
                '.note{font-size:13px;color:#7b847c;}',
                '.brand{font-size:15px;font-weight:700;color:#173029;}',
                '@media print{body{background:#ffffff;}.sheet{width:auto;margin:0;border:0;border-radius:0;box-shadow:none;}}',
                '@media (max-width:820px){.sheet{width:auto;margin:0;border-radius:0;}.content{grid-template-columns:1fr;}.footer{flex-direction:column;align-items:flex-start;}}',
                '</style>',
                '</head>',
                '<body>',
                '<div class="sheet">',
                '<div class="hero">',
                '<div class="eyebrow">Scan To Book</div>',
                '<div class="title">HYVE Coworking Space</div>',
                '<p class="copy">Open the HYVE website instantly and head straight to the booking page. This poster is ready for reception desks, lobby counters, and guest-facing tables.</p>',
                '</div>',
                '<div class="content">',
                '<div>',
                '<div class="qr-wrap"><img src="' + qrImage + '" alt="HYVE Website QR Code"></div>',
                '<div class="link">' + websiteUrl + '</div>',
                '</div>',
                '<div class="side-card">',
                '<div class="side-label">Quick Access</div>',
                '<div class="side-title">Book, browse, and connect with HYVE in one scan.</div>',
                '<p class="side-copy">Guests can use their phone camera to view rooms, review schedules, and continue directly to the website without typing the link manually.</p>',
                '<div class="steps">',
                '<div class="step"><div class="step-badge">1</div><div class="step-copy">Open the phone camera or QR scanner.</div></div>',
                '<div class="step"><div class="step-badge">2</div><div class="step-copy">Scan the HYVE QR code shown on this poster.</div></div>',
                '<div class="step"><div class="step-badge">3</div><div class="step-copy">Tap the link and proceed to the HYVE website.</div></div>',
                '</div>',
                '</div>',
                '</div>',
                '<div class="footer">',
                '<div class="brand">HYVE Coworking Space - Mandaue City</div>',
                '<div class="note">For best results, print on clean white paper or card stock.</div>',
                '</div>',
                '</div>',
                '</body>',
                '</html>'
            ].join('');

            printWindow.document.write(html);
            printWindow.document.close();
            printWindow.focus();
            printWindow.print();
        }
    </script>
@endsection
