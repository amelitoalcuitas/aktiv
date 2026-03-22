<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Booking</title>
    <style>
        body { font-family: sans-serif; background: #f9fdf2; margin: 0; padding: 40px 20px; color: #1a1a1a; }
        .container { max-width: 480px; margin: 0 auto; background: #fff; border-radius: 8px; padding: 40px; }
        .header { text-align: center; margin-bottom: 28px; }
        .icon { display: inline-block; width: 48px; height: 48px; background: #dbeafe; border-radius: 50%; line-height: 48px; font-size: 24px; margin-bottom: 12px; }
        h1 { margin: 0 0 4px; font-size: 1.25rem; color: #0f1728; }
        .subtitle { color: #64748b; font-size: 0.875rem; margin: 0; }
        .details { background: #f9fdf2; border: 1px solid #dbe4ef; border-radius: 8px; padding: 16px; margin: 24px 0; font-size: 0.875rem; }
        .details table { width: 100%; border-collapse: collapse; }
        .details td { padding: 4px 0; color: #64748b; }
        .details td:last-child { text-align: right; font-weight: 500; color: #0f1728; }
        .details .price td:last-child { color: #004e89; font-weight: 700; }
        .code { font-size: 1.5rem; font-weight: 700; letter-spacing: 0.2em; color: #0f1728; margin: 4px 0; font-family: monospace; }
        .footer { font-size: 0.75rem; color: #94a3b8; margin-top: 32px; text-align: center; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="icon">📋</div>
            <h1>New Booking Received</h1>
            <p class="subtitle">A new booking has been made at <strong>{{ $hub->name }}</strong>.</p>
        </div>

        <div class="details">
            <table>
                <tr>
                    <td>Customer</td>
                    <td>{{ $booking->bookedBy?->name ?? $booking->guest_name ?? 'Guest' }}</td>
                </tr>
                @if ($booking->bookedBy?->email ?? $booking->guest_email)
                <tr>
                    <td>Email</td>
                    <td>{{ $booking->bookedBy?->email ?? $booking->guest_email }}</td>
                </tr>
                @endif
                <tr>
                    <td>Court</td>
                    <td>{{ $courtName }}</td>
                </tr>
                <tr>
                    <td>Date</td>
                    <td>{{ \Carbon\Carbon::parse($booking->start_time)->timezone('Asia/Manila')->isoFormat('ddd, MMM D, YYYY') }}</td>
                </tr>
                <tr>
                    <td>Time</td>
                    <td>
                        {{ \Carbon\Carbon::parse($booking->start_time)->timezone('Asia/Manila')->format('g:i A') }}
                        –
                        {{ \Carbon\Carbon::parse($booking->end_time)->timezone('Asia/Manila')->format('g:i A') }}
                    </td>
                </tr>
                @if ($booking->total_price)
                <tr class="price">
                    <td>Amount</td>
                    <td>₱{{ number_format($booking->total_price, 2) }}</td>
                </tr>
                @endif
                <tr>
                    <td>Booking Code</td>
                    <td><span class="code">{{ $booking->booking_code }}</span></td>
                </tr>
            </table>
        </div>

        <div style="text-align:center;margin:28px 0 8px;">
            <a href="{{ $frontendUrl }}/dashboard/bookings?hubId={{ $hub->id }}&bookingId={{ $booking->id }}" style="display:inline-block;background:#004e89;color:#fff;text-decoration:none;padding:12px 28px;border-radius:6px;font-size:0.9375rem;font-weight:600;">Go to Dashboard</a>
        </div>

        <p class="footer">
            This is an automated message from Aktiv.
        </p>
    </div>
</body>
</html>
