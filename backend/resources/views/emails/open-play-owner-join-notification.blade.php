@extends('emails.layout')

@section('title', 'New Open Play Participant — Aktiv')

@section('content')
    @php
        $booking = $session->booking;
        $hub = $booking->court->hub;
        $courtName = $booking->court->name;
        $sport = $session->sport ?? 'Open Play';
        $participantName = $participant->user?->name ?? $participant->guest_name ?? 'Guest';
        $participantEmail = $participant->user?->email ?? $participant->guest_email;
    @endphp

    <div style="text-align:center; margin-bottom:28px;">
        <div style="display:inline-block; width:48px; height:48px; background:#dbeafe; border-radius:50%; line-height:48px; font-size:24px; margin-bottom:12px;">🏸</div>
        <h1 style="margin:0 0 4px; font-size:1.25rem; color:#0f1728;">New Open Play Participant</h1>
        <p style="color:#64748b; font-size:0.875rem; margin:0;"><strong>{{ $participantName }}</strong> joined your <strong>{{ $sport }}</strong> session at <strong>{{ $hub->name }}</strong>.</p>
    </div>

    <div style="background:#f0f4f8; border-radius:8px; padding:16px; margin:24px 0; font-size:0.875rem;">
        <table style="width:100%; border-collapse:collapse;">
            <tr>
                <td style="padding:4px 0; color:#64748b;">Participant</td>
                <td style="padding:4px 0; text-align:right; font-weight:500; color:#0f1728;">{{ $participantName }}</td>
            </tr>
            @if ($participantEmail)
            <tr>
                <td style="padding:4px 0; color:#64748b;">Email</td>
                <td style="padding:4px 0; text-align:right; font-weight:500; color:#0f1728;">{{ $participantEmail }}</td>
            </tr>
            @endif
            <tr>
                <td style="padding:4px 0; color:#64748b;">Court</td>
                <td style="padding:4px 0; text-align:right; font-weight:500; color:#0f1728;">{{ $courtName }}</td>
            </tr>
            <tr>
                <td style="padding:4px 0; color:#64748b;">Date</td>
                <td style="padding:4px 0; text-align:right; font-weight:500; color:#0f1728;">{{ \Carbon\Carbon::parse($booking->start_time)->timezone($hub->timezone_name)->isoFormat('ddd, MMM D, YYYY') }}</td>
            </tr>
            <tr>
                <td style="padding:4px 0; color:#64748b;">Time</td>
                <td style="padding:4px 0; text-align:right; font-weight:500; color:#0f1728;">
                    {{ \Carbon\Carbon::parse($booking->start_time)->timezone($hub->timezone_name)->format('g:i A') }}
                    –
                    {{ \Carbon\Carbon::parse($booking->end_time)->timezone($hub->timezone_name)->format('g:i A') }}
                </td>
            </tr>
            <tr>
                <td style="padding:4px 0; color:#64748b;">Payment</td>
                <td style="padding:4px 0; text-align:right; font-weight:700; color:#004e89;">
                    @if ($participant->payment_method === 'pay_on_site')
                        Pay on site
                    @else
                        {{ (float) $session->price_per_player === 0.0 ? 'Free' : '₱' . number_format($session->price_per_player, 2) }}
                    @endif
                </td>
            </tr>
        </table>
    </div>

    <div style="text-align:center; margin:28px 0 8px;">
        <a href="{{ $frontendUrl }}/hubs/{{ $hub->id }}/bookings" style="display:inline-block; background:#004e89; color:#fff; text-decoration:none; padding:12px 28px; border-radius:6px; font-size:0.9375rem; font-weight:600;">View in Dashboard</a>
    </div>
@endsection

@section('footer')
    This is an automated message from Aktiv for hub owners.
@endsection
