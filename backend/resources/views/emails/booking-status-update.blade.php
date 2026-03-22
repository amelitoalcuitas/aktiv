<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking Update</title>
    <style>
        body { font-family: sans-serif; background: #f9fdf2; margin: 0; padding: 40px 20px; color: #1a1a1a; }
        .container { max-width: 480px; margin: 0 auto; background: #fff; border-radius: 8px; padding: 40px; }
        .header { text-align: center; margin-bottom: 28px; }
        .icon { display: inline-block; width: 48px; height: 48px; border-radius: 50%; line-height: 48px; font-size: 24px; margin-bottom: 12px; }
        .icon-confirmed { background: #dcfce7; }
        .icon-rejected  { background: #fff7ed; }
        .icon-cancelled { background: #fee2e2; }
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
        .note { border-radius: 8px; padding: 14px 16px; font-size: 0.8125rem; margin: 20px 0; }
        .note-orange { background: #fff7ed; border: 1px solid #fed7aa; color: #9a3412; }
        .note-red    { background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; }
        .cta { text-align: center; margin: 28px 0 8px; }
        .cta a { display: inline-block; background: #004e89; color: #fff; text-decoration: none; padding: 12px 28px; border-radius: 6px; font-size: 0.9375rem; font-weight: 600; }
        .footer { font-size: 0.75rem; color: #94a3b8; margin-top: 32px; text-align: center; }
    </style>
</head>
<body>
    <div class="container">

        @if (($activityType ?? null) === 'booking_updated')
        {{-- ── Updated (time/court changed by owner) ──────────────── --}}
        <div class="header">
            <div class="icon icon-confirmed">✎</div>
            <h1>Booking Updated</h1>
            <p class="subtitle">Your booking at <strong>{{ $hub->name }}</strong> has been updated by the hub.</p>
        </div>

        <div class="details">
            <table>
                <tr><td>Court</td><td>{{ $courtName }}</td></tr>
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
                <tr><td>Booking Code</td><td style="font-family:monospace;">{{ $booking->booking_code }}</td></tr>
                @if ($booking->total_price)
                <tr class="price"><td>Amount</td><td>₱{{ number_format($booking->total_price, 2) }}</td></tr>
                @endif
            </table>
        </div>

    @elseif ($booking->status === 'confirmed')
            {{-- ── Confirmed ──────────────────────────────────────────── --}}
            <div class="header">
                <div class="icon icon-confirmed">✓</div>
                <h1>Booking Confirmed!</h1>
                <p class="subtitle">Your booking at <strong>{{ $hub->name }}</strong> has been confirmed.</p>
            </div>

            <div class="details">
                <table>
                    <tr><td>Court</td><td>{{ $courtName }}</td></tr>
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
                    <tr class="price"><td>Amount</td><td>₱{{ number_format($booking->total_price, 2) }}</td></tr>
                    @endif
                </table>
            </div>

            <div class="qr-section">
                <p class="qr-label">Scan at the venue</p>
                <div class="qr-img">
                    <img src="{{ url('/api/bookings/' . $booking->booking_code . '/qr') }}" width="180" height="180" alt="Booking QR code" />
                </div>
                <div class="code">{{ $booking->booking_code }}</div>
                <div class="code-label">Booking Code</div>
            </div>

        @elseif ($booking->status === 'pending_payment')
            {{-- ── Rejected (reset to pending_payment) ────────────────── --}}
            <div class="header">
                <div class="icon icon-rejected">⚠</div>
                <h1>Receipt Not Accepted</h1>
                <p class="subtitle">Your payment receipt for <strong>{{ $hub->name }}</strong> was not accepted.</p>
            </div>

            <div class="details">
                <table>
                    <tr><td>Court</td><td>{{ $courtName }}</td></tr>
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
                    <tr><td>Booking Code</td><td style="font-family:monospace;">{{ $booking->booking_code }}</td></tr>
                </table>
            </div>

            @if ($booking->payment_note)
            <div class="note note-orange">
                <strong>Note from {{ $hub->name }}:</strong> {{ $booking->payment_note }}
            </div>
            @endif

            <p style="font-size:0.875rem;color:#64748b;">Please re-upload your receipt before your booking expires.</p>

        @else
            {{-- ── Cancelled ───────────────────────────────────────────── --}}
            <div class="header">
                <div class="icon icon-cancelled">✕</div>
                <h1>Booking Cancelled</h1>
                <p class="subtitle">Your booking at <strong>{{ $hub->name }}</strong> has been cancelled.</p>
            </div>

            <div class="details">
                <table>
                    <tr><td>Court</td><td>{{ $courtName }}</td></tr>
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
                    <tr><td>Booking Code</td><td style="font-family:monospace;">{{ $booking->booking_code }}</td></tr>
                </table>
            </div>

            @if ($booking->payment_note)
            <div class="note note-red">
                <strong>Note:</strong> {{ $booking->payment_note }}
            </div>
            @endif
        @endif

        @if($booking->guest_email && $booking->guest_tracking_token)
        <div class="cta">
            <a href="{{ $frontendUrl }}/booking/track/{{ $booking->guest_tracking_token }}">Track Your Booking</a>
        </div>
        @elseif(!$booking->guest_email)
        <div class="cta">
            <a href="{{ $frontendUrl }}/bookings?bookingId={{ $booking->id }}">View My Booking</a>
        </div>
        @endif

        <p class="footer">
            This is an automated message from Aktiv. If you have questions, please contact {{ $hub->name }} directly.
        </p>
    </div>
</body>
</html>
