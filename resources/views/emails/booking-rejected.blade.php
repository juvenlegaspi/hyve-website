<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Booking Update</title>
</head>
<body style="margin:0; padding:24px; background:#f7f3ea; font-family:Arial, sans-serif; color:#17322c;">
    <div style="max-width:680px; margin:0 auto; background:#ffffff; border-radius:20px; padding:32px; border:1px solid #e8decb;">
        <p style="margin:0 0 8px; font-size:12px; letter-spacing:0.18em; text-transform:uppercase; color:#b18a3d;">
            HYVE Workspace
        </p>

        <h1 style="margin:0 0 10px; font-size:28px; line-height:1.2;">
            Booking update
        </h1>

        <p style="margin:0 0 20px; font-size:16px; line-height:1.7; color:#50635e;">
            Hi {{ $context['customer_name'] }}, we were unable to confirm your booking request. The booking below has been marked as rejected.
        </p>

        <div style="margin:0 0 24px; padding:18px 20px; border-radius:16px; background:#f9ece8;">
            <div style="font-size:12px; text-transform:uppercase; letter-spacing:0.12em; color:#8c5d55; margin-bottom:6px;">Reference number</div>
            <div style="font-size:24px; font-weight:700;">{{ $context['reference_no'] }}</div>
        </div>

        <div style="margin-bottom:24px;">
            <div style="font-size:18px; font-weight:700; margin-bottom:12px;">Booking details</div>

            @foreach ($context['lines'] as $line)
                <div style="padding:14px 16px; border:1px solid #e8decb; border-radius:14px; margin-bottom:10px;">
                    <div style="font-size:16px; font-weight:700; margin-bottom:4px;">{{ $line['room_name'] ?? $line['room'] ?? 'Room' }}</div>
                    <div style="font-size:14px; color:#50635e;">{{ $line['date'] }} | {{ $line['time'] }}</div>
                </div>
            @endforeach
        </div>

        <table role="presentation" style="width:100%; border-collapse:collapse; margin-bottom:24px;">
            <tr>
                <td style="padding:8px 0; color:#6d7f77;">Booking type</td>
                <td style="padding:8px 0; text-align:right; font-weight:700;">{{ $context['booking_type'] }}</td>
            </tr>
            <tr>
                <td style="padding:8px 0; color:#6d7f77;">Payment method</td>
                <td style="padding:8px 0; text-align:right; font-weight:700;">{{ $context['payment_method'] }}</td>
            </tr>
        </table>

        <p style="margin:0 0 10px; font-size:14px; line-height:1.7; color:#50635e;">
            If you would like to request a different schedule or clarify the booking details, you may reply to this email and our team will be happy to assist.
        </p>

        <p style="margin:0; font-size:14px; line-height:1.7; color:#50635e;">
            Please keep your reference number for any follow-up about this request.
        </p>
    </div>
</body>
</html>
