<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Booking Rescheduled</title>
</head>
<body style="margin:0; padding:24px; background:#f7f3ea; font-family:Arial, sans-serif; color:#17322c;">
    <div style="max-width:680px; margin:0 auto; background:#ffffff; border-radius:20px; padding:32px; border:1px solid #e8decb;">
        <p style="margin:0 0 8px; font-size:12px; letter-spacing:0.18em; text-transform:uppercase; color:#b18a3d;">HYVE Workspace</p>
        <h1 style="margin:0 0 10px; font-size:28px; line-height:1.2;">Your booking was rescheduled</h1>
        <p style="margin:0 0 20px; font-size:16px; line-height:1.7; color:#50635e;">
            Hi {{ $context['customer_name'] }}, the HYVE team updated your reserved schedule. Your booking reference and payment history remain the same.
        </p>

        <div style="margin:0 0 24px; padding:18px 20px; border-radius:16px; background:#edf6df;">
            <div style="font-size:12px; text-transform:uppercase; letter-spacing:0.12em; color:#6d7f77; margin-bottom:6px;">Reference number</div>
            <div style="font-size:24px; font-weight:700;">{{ $context['reference_no'] }}</div>
        </div>

        <table role="presentation" style="width:100%; border-collapse:collapse; margin-bottom:24px;">
            <tr>
                <td style="width:50%; padding:16px; vertical-align:top; border:1px solid #e8decb; border-radius:14px;">
                    <div style="font-size:12px; color:#8b897f; text-transform:uppercase; letter-spacing:0.1em; margin-bottom:8px;">Previous schedule</div>
                    <strong>{{ $context['old_room'] }}</strong>
                    <div style="margin-top:5px; color:#50635e; font-size:14px;">{{ $context['old_schedule'] }}</div>
                </td>
                <td style="width:50%; padding:16px; vertical-align:top; border:1px solid #cfe0c3; background:#f5faee; border-radius:14px;">
                    <div style="font-size:12px; color:#5f7d50; text-transform:uppercase; letter-spacing:0.1em; margin-bottom:8px;">New schedule</div>
                    <strong>{{ $context['new_room'] }}</strong>
                    <div style="margin-top:5px; color:#365d3a; font-size:14px;">{{ $context['new_schedule'] }}</div>
                </td>
            </tr>
        </table>

        <table role="presentation" style="width:100%; border-collapse:collapse; margin-bottom:24px;">
            <tr><td style="padding:8px 0; color:#6d7f77;">Updated booking total</td><td style="padding:8px 0; text-align:right; font-weight:700;">Php {{ number_format((float) $context['total_amount'], 2) }}</td></tr>
            <tr><td style="padding:8px 0; color:#6d7f77;">Approved payments</td><td style="padding:8px 0; text-align:right; font-weight:700;">Php {{ number_format((float) $context['paid_amount'], 2) }}</td></tr>
            <tr><td style="padding:8px 0; color:#6d7f77;">Remaining balance</td><td style="padding:8px 0; text-align:right; font-weight:700;">Php {{ number_format((float) $context['balance_amount'], 2) }}</td></tr>
        </table>

        @if ((float) ($context['overpayment'] ?? 0) > 0)
            <p style="padding:14px 16px; border-radius:12px; background:#fff5dc; color:#765b22; line-height:1.6;">
                Your approved payments are Php {{ number_format((float) $context['overpayment'], 2) }} above the updated total. HYVE will review the excess amount with you.
            </p>
        @endif

        <p style="margin:0; font-size:14px; line-height:1.7; color:#50635e;">If you have questions about this change, reply to this email or contact the HYVE front desk.</p>
    </div>
</body>
</html>
