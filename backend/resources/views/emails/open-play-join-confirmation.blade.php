@extends('emails.layout')

@section('title', "You've Joined Open Play — Aktiv")

@section('content')
    @php
        $booking = $session->booking;
        $hub = $booking->court->hub;
        $courtName = $booking->court->name;
        $sport = $session->sport ?? 'Open Play';
        $isFree = (float) $session->price_per_player === 0.0;
        $isPayOnSite = $participant->payment_method === 'pay_on_site' && ! $isFree;
        $trackingUrl = $participant->guest_tracking_token
            ? "{$frontendUrl}/open-play/track/{$participant->guest_tracking_token}"
            : "{$frontendUrl}/hubs/{$hub->id}/open-play";
        $ctaLabel = $participant->guest_tracking_token ? 'Manage Join' : 'View Session';
    @endphp

    <div style="text-align:center; margin-bottom:28px;">
        <div style="display:inline-block; width:48px; height:48px; background:#dcfce7; border-radius:50%; line-height:48px; font-size:24px; margin-bottom:12px;">✓</div>
        <h1 style="margin:0 0 4px; font-size:1.25rem; color:#0f1728;">
            {{ $isPayOnSite ? 'Pending Venue Confirmation' : 'Spot Confirmed' }}
        </h1>
        <p style="color:#64748b; font-size:0.875rem; margin:0;">
            @if ($isPayOnSite)
                Your join for <strong>{{ $sport }}</strong> at <strong>{{ $hub->name }}</strong> is pending venue confirmation.
            @else
                Your spot is confirmed for <strong>{{ $sport }}</strong> at <strong>{{ $hub->name }}</strong>.
            @endif
        </p>
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
            <tr>
                <td style="padding:4px 0; color:#64748b;">Payment</td>
                <td style="padding:4px 0; text-align:right; font-weight:500; color:#0f1728;">
                    @if ($participant->payment_method === 'pay_on_site')
                        Pay on site
                    @else
                        {{ (float) $session->price_per_player === 0.0 ? 'Free' : '₱' . number_format($session->price_per_player, 2) }}
                    @endif
                </td>
            </tr>
            @if ($session->notes)
            <tr>
                <td style="padding:4px 0; color:#64748b; vertical-align:top;">Notes</td>
                <td style="padding:4px 0; text-align:right; font-weight:500; color:#0f1728;">{{ $session->notes }}</td>
            </tr>
            @endif
        </table>
    </div>

    <div style="background:{{ $isPayOnSite ? '#fefce8' : '#ecfdf5' }}; border:1px solid {{ $isPayOnSite ? '#fde047' : '#86efac' }}; border-radius:8px; padding:14px 16px; font-size:0.8125rem; color:{{ $isPayOnSite ? '#713f12' : '#166534' }}; margin:20px 0;">
        @if ($isPayOnSite)
            Your status is <strong>Pending Venue Confirmation</strong>. The venue may still review your join before the session starts.
        @else
            Your status is <strong>Confirmed</strong>. You're all set for the session.
        @endif
    </div>

    <div style="text-align:center; margin:28px 0 8px;">
        <a href="{{ $trackingUrl }}" style="display:inline-block; background:#004e89; color:#fff; text-decoration:none; padding:12px 28px; border-radius:6px; font-size:0.9375rem; font-weight:600;">{{ $ctaLabel }}</a>
    </div>
@endsection

@section('footer')
    This is an automated message from Aktiv. If you have questions, please contact {{ $hub->name }} directly.
@endsection
