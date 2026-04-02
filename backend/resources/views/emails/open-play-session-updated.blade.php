@extends('emails.layout')

@section('title', 'Open Play Session Updated — Aktiv')

@section('content')
    @php
        $booking = $session->booking;
        $hub = $booking->court->hub;
        $newCourtName = $booking->court->name;
        $trackingUrl = $participant->guest_tracking_token
            ? "{$frontendUrl}/open-play/track/{$participant->guest_tracking_token}"
            : "{$frontendUrl}/hubs/{$hub->id}/open-play";
        $ctaLabel = $participant->guest_tracking_token ? 'View Join Details' : 'View Session';
    @endphp

    <div style="text-align:center; margin-bottom:28px;">
        <div style="display:inline-block; width:48px; height:48px; background:#dbeafe; border-radius:50%; line-height:48px; font-size:24px; margin-bottom:12px;">📣</div>
        <h1 style="margin:0 0 4px; font-size:1.25rem; color:#0f1728;">Session Updated</h1>
        <p style="color:#64748b; font-size:0.875rem; margin:0;">
            <strong>{{ $hub->name }}</strong> updated the details for <strong>{{ $session->title }}</strong>.
        </p>
    </div>

    <div style="background:#f0f4f8; border-radius:8px; padding:16px; margin:24px 0; font-size:0.875rem;">
        <table style="width:100%; border-collapse:collapse;">
            <tr>
                <td style="padding:4px 0; color:#64748b;">Previous court</td>
                <td style="padding:4px 0; text-align:right; font-weight:500; color:#0f1728;">{{ $originalCourtName }}</td>
            </tr>
            <tr>
                <td style="padding:4px 0; color:#64748b;">Previous schedule</td>
                <td style="padding:4px 0; text-align:right; font-weight:500; color:#0f1728;">
                    {{ $originalStartTime->copy()->timezone($booking->court->hub->timezone_name)->isoFormat('ddd, MMM D, YYYY') }}<br>
                    {{ $originalStartTime->copy()->timezone($booking->court->hub->timezone_name)->format('g:i A') }} –
                    {{ $originalEndTime->copy()->timezone($booking->court->hub->timezone_name)->format('g:i A') }}
                </td>
            </tr>
            <tr>
                <td style="padding:8px 0 4px; color:#64748b;">New court</td>
                <td style="padding:8px 0 4px; text-align:right; font-weight:700; color:#0f1728;">{{ $newCourtName }}</td>
            </tr>
            <tr>
                <td style="padding:4px 0; color:#64748b;">New schedule</td>
                <td style="padding:4px 0; text-align:right; font-weight:700; color:#004e89;">
                    {{ \Carbon\Carbon::parse($booking->start_time)->timezone($booking->court->hub->timezone_name)->isoFormat('ddd, MMM D, YYYY') }}<br>
                    {{ \Carbon\Carbon::parse($booking->start_time)->timezone($booking->court->hub->timezone_name)->format('g:i A') }} –
                    {{ \Carbon\Carbon::parse($booking->end_time)->timezone($booking->court->hub->timezone_name)->format('g:i A') }}
                </td>
            </tr>
        </table>
    </div>

    <p style="font-size:0.875rem; color:#64748b; text-align:center;">
        Please review the updated details before the session starts.
    </p>

    <div style="text-align:center; margin:28px 0 8px;">
        <a href="{{ $trackingUrl }}" style="display:inline-block; background:#004e89; color:#fff; text-decoration:none; padding:12px 28px; border-radius:6px; font-size:0.9375rem; font-weight:600;">{{ $ctaLabel }}</a>
    </div>
@endsection

@section('footer')
    This is an automated message from Aktiv. If you have questions, please contact {{ $hub->name }} directly.
@endsection
