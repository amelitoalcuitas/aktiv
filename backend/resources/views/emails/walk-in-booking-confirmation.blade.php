<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Walk-in Booking Confirmed</title>
    <style>
        body { font-family: sans-serif; background: #f9fdf2; margin: 0; padding: 40px 20px; color: #1a1a1a; }
        .container { max-width: 480px; margin: 0 auto; background: #fff; border-radius: 8px; padding: 40px; }
        .header { text-align: center; margin-bottom: 28px; }
        .check { display: inline-block; width: 48px; height: 48px; background: #dcfce7; border-radius: 50%; line-height: 48px; font-size: 24px; margin-bottom: 12px; }
        h1 { margin: 0 0 4px; font-size: 1.25rem; color: #0f1728; }
        .subtitle { color: #64748b; font-size: 0.875rem; margin: 0; }
        .details { background: #f9fdf2; border: 1px solid #dbe4ef; border-radius: 8px; padding: 16px; margin: 24px 0; font-size: 0.875rem; }
        .details table { width: 100%; border-collapse: collapse; }
        .details td { padding: 4px 0; color: #64748b; }
        .details td:last-child { text-align: right; font-weight: 500; color: #0f1728; }
        .details .price td:last-child { color: #004e89; font-weight: 700; }
        .qr-section { text-align: center; margin: 24px 0; }
        .qr-label { font-size: 0.75rem; color: #64748b; margin-bottom: 8px; }
        .qr-img { display: inline-block; border: 1px solid #dbe4ef; border-radius: 8px; padding: 12px; background: #fff; }
        .code { font-size: 2rem; font-weight: 700; letter-spacing: 0.25em; color: #0f1728; margin: 12px 0 4px; font-family: monospace; }
        .code-label { font-size: 0.75rem; color: #64748b; }
        .badge { display: inline-block; background: #dcfce7; color: #166534; font-size: 0.75rem; font-weight: 600; padding: 4px 10px; border-radius: 999px; margin-bottom: 16px; }
        .instructions { background: #f0f7ff; border: 1px solid #bfdbfe; border-radius: 8px; padding: 14px 16px; font-size: 0.8125rem; color: #1e40af; margin: 20px 0; }
        .footer { font-size: 0.75rem; color: #94a3b8; margin-top: 32px; text-align: center; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="check">✓</div>
            <span class="badge">Confirmed</span>
            <h1>Walk-in Booking Confirmed</h1>
            <p class="subtitle">Your walk-in session at <strong>{{ $hub->name }}</strong> is all set.</p>
        </div>

        <div class="details">
            <table>
                @if ($booking->guest_name)
                <tr>
                    <td>Name</td>
                    <td>{{ $booking->guest_name }}</td>
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
            </table>
        </div>

        <div class="qr-section">
            <p class="qr-label">Your booking code</p>
            <div class="code">{{ $booking->booking_code }}</div>
            <div class="code-label">Show this to the hub owner if needed</div>
        </div>

        <div class="instructions">
            This booking has been confirmed by the hub owner. No payment receipt is required — just show up and enjoy your session!
        </div>

        @if(isset($guestTrackingToken) && $guestTrackingToken)
        <div style="text-align:center;margin:28px 0 8px;">
            <a href="{{ $frontendUrl }}/booking/track/{{ $guestTrackingToken }}" style="display:inline-block;background:#004e89;color:#fff;text-decoration:none;padding:12px 28px;border-radius:6px;font-size:0.9375rem;font-weight:600;">Track Your Booking</a>
        </div>
        @endif

        <p class="footer">
            If you have questions, contact <strong>{{ $hub->name }}</strong> directly.<br>
            This is an automated message from Aktiv.
        </p>
    </div>
</body>
</html>
