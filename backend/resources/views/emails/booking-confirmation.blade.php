@extends('emails.layout')

@section('title', 'Booking Confirmed — Aktiv')

@section('content')
    <div style="text-align:center; margin-bottom:28px;">
        <div style="display:inline-block; width:48px; height:48px; background:#dcfce7; border-radius:50%; line-height:48px; font-size:24px; margin-bottom:12px;">✓</div>
        <h1 style="margin:0 0 4px; font-size:1.25rem; color:#0f1728;">Booking Confirmed!</h1>
        <p style="color:#64748b; font-size:0.875rem; margin:0;">Show this QR code or booking code at <strong>{{ $hub->name }}</strong>.</p>
    </div>

    <div style="background:#f0f4f8; border-radius:8px; padding:16px; margin:24px 0; font-size:0.875rem;">
        <table style="width:100%; border-collapse:collapse;">
            <tr>
                <td style="padding:4px 0; color:#64748b;">Court</td>
                <td style="padding:4px 0; text-align:right; font-weight:500; color:#0f1728;">{{ $courtName }}</td>
            </tr>
            <tr>
                <td style="padding:4px 0; color:#64748b;">Date</td>
                <td style="padding:4px 0; text-align:right; font-weight:500; color:#0f1728;">{{ \Carbon\Carbon::parse($booking->start_time)->timezone($booking->court->hub->timezone_name)->isoFormat('ddd, MMM D, YYYY') }}</td>
            </tr>
            <tr>
                <td style="padding:4px 0; color:#64748b;">Time</td>
                <td style="padding:4px 0; text-align:right; font-weight:500; color:#0f1728;">
                    {{ \Carbon\Carbon::parse($booking->start_time)->timezone($booking->court->hub->timezone_name)->format('g:i A') }}
                    –
                    {{ \Carbon\Carbon::parse($booking->end_time)->timezone($booking->court->hub->timezone_name)->format('g:i A') }}
                </td>
            </tr>
            @if ($booking->total_price)
            <tr>
                <td style="padding:4px 0; color:#64748b;">Amount</td>
                <td style="padding:4px 0; text-align:right; font-weight:700; color:#004e89;">₱{{ number_format($booking->total_price, 2) }}</td>
            </tr>
            @endif
        </table>
    </div>

    <div style="text-align:center; margin:24px 0;">
        <p style="font-size:0.75rem; color:#64748b; margin-bottom:8px;">Scan at the venue</p>
        <div style="display:inline-block; border:1px solid #dbe4ef; border-radius:8px; padding:12px; background:#fff;">
            <img src="{{ url('/api/bookings/' . $booking->booking_code . '/qr') }}" width="180" height="180" alt="Booking QR code" />
        </div>
        <div style="font-size:2rem; font-weight:700; letter-spacing:0.25em; color:#0f1728; margin:12px 0 4px; font-family:monospace;">{{ $booking->booking_code }}</div>
        <div style="font-size:0.75rem; color:#64748b;">Booking Code</div>
    </div>

    <div style="background:#f0f7ff; border:1px solid #bfdbfe; border-radius:8px; padding:14px 16px; font-size:0.8125rem; color:#1e40af; margin:20px 0;">
        Show the QR code or tell the hub owner your booking code when you arrive. They'll scan or enter it to confirm your payment on site.
    </div>

    @if(isset($guestTrackingToken) && $guestTrackingToken)
    <div style="text-align:center; margin:28px 0 8px;">
        <a href="{{ $frontendUrl }}/booking/track/{{ $guestTrackingToken }}" style="display:inline-block; background:#004e89; color:#fff; text-decoration:none; padding:12px 28px; border-radius:6px; font-size:0.9375rem; font-weight:600;">Track Your Booking</a>
    </div>
    @else
    <div style="text-align:center; margin:28px 0 8px;">
        <a href="{{ $frontendUrl }}/bookings" style="display:inline-block; background:#004e89; color:#fff; text-decoration:none; padding:12px 28px; border-radius:6px; font-size:0.9375rem; font-weight:600;">View My Bookings</a>
    </div>
    @endif
@endsection

@section('footer')
    If you did not make this booking, please contact {{ $hub->name }} directly.<br>
    This is an automated message from Aktiv.
@endsection
