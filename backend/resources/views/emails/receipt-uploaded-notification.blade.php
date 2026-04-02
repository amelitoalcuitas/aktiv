@extends('emails.layout')

@section('title', 'Receipt Uploaded — Aktiv')

@section('content')
    <div style="text-align:center; margin-bottom:28px;">
        <div style="display:inline-block; width:48px; height:48px; background:#fef9c3; border-radius:50%; line-height:48px; font-size:24px; margin-bottom:12px;">🧾</div>
        <h1 style="margin:0 0 4px; font-size:1.25rem; color:#0f1728;">Payment Receipt Uploaded</h1>
        <p style="color:#64748b; font-size:0.875rem; margin:0;">A customer has uploaded a receipt for their booking at <strong>{{ $hub->name }}</strong>.</p>
    </div>

    <div style="background:#f0f4f8; border-radius:8px; padding:16px; margin:24px 0; font-size:0.875rem;">
        <table style="width:100%; border-collapse:collapse;">
            <tr>
                <td style="padding:4px 0; color:#64748b;">Customer</td>
                <td style="padding:4px 0; text-align:right; font-weight:500; color:#0f1728;">{{ $booking->bookedBy?->name ?? $booking->guest_name ?? 'Guest' }}</td>
            </tr>
            @if ($booking->bookedBy?->email ?? $booking->guest_email)
            <tr>
                <td style="padding:4px 0; color:#64748b;">Email</td>
                <td style="padding:4px 0; text-align:right; font-weight:500; color:#0f1728;">{{ $booking->bookedBy?->email ?? $booking->guest_email }}</td>
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
            <tr>
                <td style="padding:4px 0; color:#64748b;">Booking Code</td>
                <td style="padding:4px 0; text-align:right; font-weight:700; color:#0f1728; font-family:monospace; font-size:1.25rem; letter-spacing:0.15em;">{{ $booking->booking_code }}</td>
            </tr>
        </table>
    </div>

    <div style="text-align:center; margin:28px 0 8px;">
        <a href="{{ $frontendUrl }}/hubs/{{ $hub->id }}/bookings?bookingId={{ $booking->id }}" style="display:inline-block; background:#004e89; color:#fff; text-decoration:none; padding:12px 28px; border-radius:6px; font-size:0.9375rem; font-weight:600;">Review in Dashboard</a>
    </div>
@endsection

@section('footer')
    Log in to confirm or reject this payment receipt.<br>
    This is an automated message from Aktiv.
@endsection
