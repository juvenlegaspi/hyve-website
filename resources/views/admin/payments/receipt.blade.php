<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Unofficial Receipt - Payment #{{ $payment->id }}</title>
    <style>
        body {
            margin: 0;
            background: #f5f1e8;
            color: #173029;
            font-family: Arial, sans-serif;
            line-height: 1.5;
        }

        .receipt-shell {
            max-width: 860px;
            margin: 32px auto;
            padding: 0 18px 32px;
        }

        .receipt-card {
            background: #fffdfa;
            border: 1px solid #e4ddd0;
            border-radius: 24px;
            box-shadow: 0 18px 45px rgba(29, 37, 32, 0.08);
            overflow: hidden;
        }

        .receipt-head {
            display: flex;
            justify-content: space-between;
            gap: 16px;
            padding: 28px 30px 22px;
            border-bottom: 1px solid #eee7da;
            background: linear-gradient(135deg, #fffdf8 0%, #f5f9ed 100%);
        }

        .receipt-eyebrow {
            margin: 0 0 8px;
            color: #9d7832;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.18em;
            text-transform: uppercase;
        }

        h1 {
            margin: 0;
            font-size: 32px;
            line-height: 1.1;
        }

        .receipt-subtitle {
            margin: 8px 0 0;
            color: #6d766f;
            font-size: 14px;
        }

        .receipt-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 8px 14px;
            border-radius: 999px;
            background: #eef8df;
            color: #3f6a34;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.08em;
        }

        .receipt-section {
            padding: 24px 30px;
            border-bottom: 1px solid #f0ebdf;
        }

        .receipt-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 18px 22px;
        }

        .receipt-grid dt {
            margin: 0 0 4px;
            color: #8f877b;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.14em;
            text-transform: uppercase;
        }

        .receipt-grid dd {
            margin: 0;
            font-size: 16px;
            font-weight: 600;
        }

        .receipt-table {
            width: 100%;
            border-collapse: collapse;
        }

        .receipt-table th,
        .receipt-table td {
            padding: 12px 0;
            border-bottom: 1px solid #f1ebdf;
            text-align: left;
            vertical-align: top;
        }

        .receipt-table th {
            color: #8f877b;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.14em;
            text-transform: uppercase;
        }

        .receipt-total {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 18px;
            padding-top: 16px;
            border-top: 2px solid #e6dfd0;
            font-size: 22px;
            font-weight: 700;
        }

        .receipt-foot {
            padding: 20px 30px 28px;
            color: #746d63;
            font-size: 13px;
        }

        .receipt-actions {
            display: flex;
            justify-content: flex-end;
            gap: 12px;
            margin-bottom: 16px;
        }

        .receipt-button {
            border: 1px solid #d8decf;
            border-radius: 999px;
            background: #fff;
            color: #173029;
            cursor: pointer;
            font-size: 14px;
            font-weight: 700;
            padding: 10px 18px;
            text-decoration: none;
        }

        .receipt-button--primary {
            background: #2f5f35;
            border-color: #2f5f35;
            color: #fff;
        }

        @media print {
            body {
                background: #fff;
            }

            .receipt-shell {
                max-width: none;
                margin: 0;
                padding: 0;
            }

            .receipt-actions {
                display: none;
            }

            .receipt-card {
                box-shadow: none;
                border: none;
                border-radius: 0;
            }
        }
    </style>
</head>
<body>
    <div class="receipt-shell">
        <div class="receipt-actions">
            <button type="button" class="receipt-button" onclick="window.close()">Close</button>
            <button type="button" class="receipt-button receipt-button--primary" onclick="window.print()">Print Receipt</button>
        </div>

        <div class="receipt-card">
            <section class="receipt-head">
                <div>
                    <p class="receipt-eyebrow">HYVE Workspace</p>
                    <h1>Unofficial Receipt</h1>
                    <p class="receipt-subtitle">Payment reference for internal and customer viewing only.</p>
                </div>
                <div style="text-align:right;">
                    <div class="receipt-badge">Paid Payment</div>
                    <p class="receipt-subtitle" style="margin-top:12px;">Receipt #: PAY-{{ str_pad((string) $payment->id, 5, '0', STR_PAD_LEFT) }}</p>
                </div>
            </section>

            <section class="receipt-section">
                <div class="receipt-grid">
                    <div>
                        <dt>Customer</dt>
                        <dd>{{ $header->customer_name }}</dd>
                    </div>
                    <div>
                        <dt>Email</dt>
                        <dd>{{ $header->email }}</dd>
                    </div>
                    <div>
                        <dt>Phone</dt>
                        <dd>{{ $header->phone }}</dd>
                    </div>
                    <div>
                        <dt>Booking Reference</dt>
                        <dd>{{ $header->reference_no }}</dd>
                    </div>
                    <div>
                        <dt>Payment Method</dt>
                        <dd>{{ ucfirst(str_replace('_', ' ', (string) $payment->payment_method)) }}</dd>
                    </div>
                    <div>
                        <dt>Payment Date</dt>
                        <dd>{{ optional($payment->verified_at ?? $payment->paid_at ?? $payment->created_at)->format('F j, Y g:i A') }}</dd>
                    </div>
                </div>
            </section>

            <section class="receipt-section">
                <table class="receipt-table">
                    <thead>
                        <tr>
                            <th>Description</th>
                            <th>Booking Period</th>
                            <th>Type</th>
                            <th style="text-align:right;">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>
                                <strong>{{ $roomName }}</strong><br>
                                <span style="color:#746d63;">{{ $payment->notes ?: 'HYVE booking payment' }}</span>
                            </td>
                            <td>
                                @if ($detail)
                                    {{ optional($detail->booking_date)->format('F j, Y') ?? '--' }}<br>
                                    {{ $detail->start_time ? \Illuminate\Support\Carbon::createFromFormat(strlen((string) $detail->start_time) === 5 ? 'H:i' : 'H:i:s', (string) $detail->start_time)->format('g:i A') : '--' }}
                                    -
                                    {{ $detail->end_time ? \Illuminate\Support\Carbon::createFromFormat(strlen((string) $detail->end_time) === 5 ? 'H:i' : 'H:i:s', (string) $detail->end_time)->format('g:i A') : '--' }}
                                @else
                                    {{ optional($details->first()?->booking_date)->format('F j, Y') ?? '--' }}
                                @endif
                            </td>
                            <td>{{ ucfirst(str_replace('_', ' ', (string) $payment->payment_type)) }}</td>
                            <td style="text-align:right;">Php {{ number_format((float) $payment->amount, 2) }}</td>
                        </tr>
                    </tbody>
                </table>

                <div class="receipt-total">
                    <span>Total Paid</span>
                    <span>Php {{ number_format((float) $payment->amount, 2) }}</span>
                </div>

                @if ((float) ($header->discount_amount ?? 0) > 0)
                    <div style="margin-top:14px; display:grid; gap:6px; color:#6c756c; font-size:13px;">
                        <div><strong style="color:#173029;">Gross total:</strong> Php {{ number_format((float) ($header->total_amount ?? 0), 2) }}</div>
                        <div><strong style="color:#173029;">Discount:</strong> {{ $header->discount_label }} - Php {{ number_format((float) ($header->discount_amount ?? 0), 2) }}</div>
                        <div><strong style="color:#173029;">Final payable total:</strong> Php {{ number_format((float) ($header->discounted_total_amount ?? $header->total_amount ?? 0), 2) }}</div>
                    </div>
                @endif
            </section>

            <section class="receipt-foot">
                This is an unofficial receipt generated by the HYVE admin payment tracker. Verified by {{ $payment->verifiedByUser?->name ?? 'Admin' }} on {{ optional($payment->verified_at ?? $payment->paid_at ?? $payment->created_at)->format('F j, Y g:i A') }}.
            </section>
        </div>
    </div>
</body>
</html>
