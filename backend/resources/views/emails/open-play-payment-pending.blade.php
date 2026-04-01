@extends('emails.layout')

@section('title', 'Complete Your Payment — Aktiv')

@section('content')
    @php
        $booking = $session->booking;
        $hub = $booking->court->hub;
        $courtName = $booking->court->name;
        $sport = $session->sport ?? 'Open Play';
        $trackingUrl = $participant->guest_tracking_token
            ? "{$frontendUrl}/open-play/track/{$participant->guest_tracking_token}"
            : "{$frontendUrl}/hubs/{$hub->id}/open-play";
    @endphp

    <div style="text-align:center; margin-bottom:28px;">
        <div style="display:inline-block; width:48px; height:48px; background:#dbeafe; border-radius:50%; line-height:48px; font-size:24px; margin-bottom:12px;">💳</div>
        <h1 style="margin:0 0 4px; font-size:1.25rem; color:#0f1728;">Complete Your Payment</h1>
        <p style="color:#64748b; font-size:0.875rem; margin:0;">You've joined <strong>{{ $sport }}</strong> at <strong>{{ $hub->name }}</strong>. Upload your receipt to confirm your spot.</p>
    </div>

    <div style="background:#f0f4f8; border-radius:8px; padding:16px; margin:24px 0; font-size:0.875rem;">
        <table style="width:100%; border-collapse:collapse;">
            <tr>
                <td style="padding:4px 0; color:#64748b;">Court</td>
                <td style="padding:4px 0; text-align:right; font-weight:500; color:#0f1728;">{{ $courtName }}</td>
            </tr>
            <tr>
                <td style="padding:4px 0; color:#64748b;">Date</td>
                <td style="padding:4px 0; text-align:right; font-weight:500; color:#0f1728;">{{ \Carbon\Carbon::parse($booking->start_time)->timezone('Asia/Manila')->isoFormat('ddd, MMM D, YYYY') }}</td>
            </tr>
            <tr>
                <td style="padding:4px 0; color:#64748b;">Time</td>
                <td style="padding:4px 0; text-align:right; font-weight:500; color:#0f1728;">
                    {{ \Carbon\Carbon::parse($booking->start_time)->timezone('Asia/Manila')->format('g:i A') }}
                    –
                    {{ \Carbon\Carbon::parse($booking->end_time)->timezone('Asia/Manila')->format('g:i A') }}
                </td>
            </tr>
            <tr>
                <td style="padding:4px 0; color:#64748b;">Amount</td>
                <td style="padding:4px 0; text-align:right; font-weight:700; color:#004e89;">₱{{ number_format($session->price_per_player, 2) }}</td>
            </tr>
            @if ($participant->expires_at)
            <tr>
                <td style="padding:4px 0; color:#64748b;">Payment deadline</td>
                <td style="padding:4px 0; text-align:right; font-weight:500; color:#dc2626;">{{ \Carbon\Carbon::parse($participant->expires_at)->timezone('Asia/Manila')->format('M j, g:i A') }}</td>
            </tr>
            @endif
        </table>
    </div>

    <div style="background:#fefce8; border:1px solid #fde047; border-radius:8px; padding:14px 16px; font-size:0.8125rem; color:#713f12; margin:20px 0;">
        Your spot is <strong>tentatively held</strong>. Upload your payment receipt before the deadline to secure it.
    </div>

    <div style="text-align:center; margin:28px 0 8px;">
        <a href="{{ $trackingUrl }}" style="display:inline-block; background:#004e89; color:#fff; text-decoration:none; padding:12px 28px; border-radius:6px; font-size:0.9375rem; font-weight:600;">Upload Receipt</a>
    </div>
@endsection

@section('footer')
    This is an automated message from Aktiv. If you have questions, please contact {{ $hub->name }} directly.
@endsection
