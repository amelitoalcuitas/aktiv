@extends('emails.layout')

@section('title', 'Walk-in Booking Confirmed — Aktiv')

@section('content')
    <div style="text-align:center; margin-bottom:28px;">
        <div style="display:inline-block; width:48px; height:48px; background:#dcfce7; border-radius:50%; line-height:48px; font-size:24px; margin-bottom:12px;">✓</div>
        <div style="display:inline-block; background:#dcfce7; color:#166534; font-size:0.75rem; font-weight:600; padding:4px 10px; border-radius:999px; margin-bottom:16px;">Confirmed</div>
        <h1 style="margin:0 0 4px; font-size:1.25rem; color:#0f1728;">Walk-in Booking Confirmed</h1>
        <p style="color:#64748b; font-size:0.875rem; margin:0;">Your walk-in session at <strong>{{ $hub->name }}</strong> is all set.</p>
    </div>

    <div style="background:#f0f4f8; border-radius:8px; padding:16px; margin:24px 0; font-size:0.875rem;">
        <table style="width:100%; border-collapse:collapse;">
            @if ($booking->guest_name)
            <tr>
                <td style="padding:4px 0; color:#64748b;">Name</td>
                <td style="padding:4px 0; text-align:right; font-weight:500; color:#0f1728;">{{ $booking->guest_name }}</td>
            </tr>
            @endif
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
        <p style="font-size:0.75rem; color:#64748b; margin-bottom:4px;">Your booking code</p>
        <div style="font-size:2rem; font-weight:700; letter-spacing:0.25em; color:#0f1728; margin:12px 0 4px; font-family:monospace;">{{ $booking->booking_code }}</div>
        <div style="font-size:0.75rem; color:#64748b;">Show this to the hub owner if needed</div>
    </div>

    <div style="background:#f0f7ff; border:1px solid #bfdbfe; border-radius:8px; padding:14px 16px; font-size:0.8125rem; color:#1e40af; margin:20px 0;">
        This booking has been confirmed by the hub owner. No payment receipt is required — just show up and enjoy your session!
    </div>

    @if(isset($guestTrackingToken) && $guestTrackingToken)
    <div style="text-align:center; margin:28px 0 8px;">
        <a href="{{ $frontendUrl }}/booking/track/{{ $guestTrackingToken }}" style="display:inline-block; background:#004e89; color:#fff; text-decoration:none; padding:12px 28px; border-radius:6px; font-size:0.9375rem; font-weight:600;">Track Your Booking</a>
    </div>
    @endif
@endsection

@section('footer')
    If you have questions, contact <strong>{{ $hub->name }}</strong> directly.<br>
    This is an automated message from Aktiv.
@endsection
