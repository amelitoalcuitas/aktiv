@extends('emails.layout')

@section('title', 'Receipt Rejected — Aktiv')

@section('content')
    @php
        $booking = $session->booking;
        $hub = $booking->court->hub;
        $courtName = $booking->court->name;
        $sport = $session->sport ?? 'Open Play';
    @endphp

    <div style="text-align:center; margin-bottom:28px;">
        <div style="display:inline-block; width:48px; height:48px; background:#fff7ed; border-radius:50%; line-height:48px; font-size:24px; margin-bottom:12px;">⚠</div>
        <h1 style="margin:0 0 4px; font-size:1.25rem; color:#0f1728;">Receipt Not Accepted</h1>
        <p style="color:#64748b; font-size:0.875rem; margin:0;">Your payment receipt for <strong>{{ $sport }}</strong> at <strong>{{ $hub->name }}</strong> was not accepted.</p>
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
        </table>
    </div>

    @if ($participant->payment_note)
    <div style="background:#fff7ed; border:1px solid #fed7aa; border-radius:8px; padding:14px 16px; font-size:0.8125rem; color:#9a3412; margin:20px 0;">
        <strong>Note from {{ $hub->name }}:</strong> {{ $participant->payment_note }}
    </div>
    @endif

    <p style="font-size:0.875rem; color:#64748b;">Please re-upload your receipt before your spot expires.</p>

    <div style="text-align:center; margin:28px 0 8px;">
        <a href="{{ $frontendUrl }}/hubs/{{ $hub->id }}/open-play" style="display:inline-block; background:#004e89; color:#fff; text-decoration:none; padding:12px 28px; border-radius:6px; font-size:0.9375rem; font-weight:600;">Re-upload Receipt</a>
    </div>
@endsection

@section('footer')
    This is an automated message from Aktiv. If you have questions, please contact {{ $hub->name }} directly.
@endsection
