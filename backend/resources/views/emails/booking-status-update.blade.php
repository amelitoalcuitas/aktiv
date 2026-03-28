@extends('emails.layout')

@section('title', 'Booking Update — Aktiv')

@section('content')
    @if (($activityType ?? null) === 'booking_updated')
    {{-- ── Updated (time/court changed by owner) ──────────────── --}}
    <div style="text-align:center; margin-bottom:28px;">
        <div style="display:inline-block; width:48px; height:48px; background:#dcfce7; border-radius:50%; line-height:48px; font-size:24px; margin-bottom:12px;">✎</div>
        <h1 style="margin:0 0 4px; font-size:1.25rem; color:#0f1728;">Booking Updated</h1>
        <p style="color:#64748b; font-size:0.875rem; margin:0;">Your booking at <strong>{{ $hub->name }}</strong> has been updated by the hub.</p>
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
                <td style="padding:4px 0; color:#64748b;">Booking Code</td>
                <td style="padding:4px 0; text-align:right; font-weight:500; color:#0f1728; font-family:monospace;">{{ $booking->booking_code }}</td>
            </tr>
            @if ($booking->total_price)
            <tr>
                <td style="padding:4px 0; color:#64748b;">Amount</td>
                <td style="padding:4px 0; text-align:right; font-weight:700; color:#004e89;">₱{{ number_format($booking->total_price, 2) }}</td>
            </tr>
            @endif
        </table>
    </div>

    @elseif ($booking->status === 'confirmed')
    {{-- ── Confirmed ──────────────────────────────────────────── --}}
    <div style="text-align:center; margin-bottom:28px;">
        <div style="display:inline-block; width:48px; height:48px; background:#dcfce7; border-radius:50%; line-height:48px; font-size:24px; margin-bottom:12px;">✓</div>
        <h1 style="margin:0 0 4px; font-size:1.25rem; color:#0f1728;">Booking Confirmed!</h1>
        <p style="color:#64748b; font-size:0.875rem; margin:0;">Your booking at <strong>{{ $hub->name }}</strong> has been confirmed.</p>
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

    @elseif ($booking->status === 'pending_payment')
    {{-- ── Rejected (reset to pending_payment) ────────────────── --}}
    <div style="text-align:center; margin-bottom:28px;">
        <div style="display:inline-block; width:48px; height:48px; background:#fff7ed; border-radius:50%; line-height:48px; font-size:24px; margin-bottom:12px;">⚠</div>
        <h1 style="margin:0 0 4px; font-size:1.25rem; color:#0f1728;">Receipt Not Accepted</h1>
        <p style="color:#64748b; font-size:0.875rem; margin:0;">Your payment receipt for <strong>{{ $hub->name }}</strong> was not accepted.</p>
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
                <td style="padding:4px 0; color:#64748b;">Booking Code</td>
                <td style="padding:4px 0; text-align:right; font-weight:500; color:#0f1728; font-family:monospace;">{{ $booking->booking_code }}</td>
            </tr>
        </table>
    </div>

    @if ($booking->payment_note)
    <div style="background:#fff7ed; border:1px solid #fed7aa; border-radius:8px; padding:14px 16px; font-size:0.8125rem; color:#9a3412; margin:20px 0;">
        <strong>Note from {{ $hub->name }}:</strong> {{ $booking->payment_note }}
    </div>
    @endif

    <p style="font-size:0.875rem; color:#64748b;">Please re-upload your receipt before your booking expires.</p>

    @else
    {{-- ── Cancelled ───────────────────────────────────────────── --}}
    <div style="text-align:center; margin-bottom:28px;">
        <div style="display:inline-block; width:48px; height:48px; background:#fee2e2; border-radius:50%; line-height:48px; font-size:24px; margin-bottom:12px;">❌</div>
        <h1 style="margin:0 0 4px; font-size:1.25rem; color:#0f1728;">Booking Cancelled</h1>
        <p style="color:#64748b; font-size:0.875rem; margin:0;">Your booking at <strong>{{ $hub->name }}</strong> has been cancelled.</p>
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
                <td style="padding:4px 0; color:#64748b;">Booking Code</td>
                <td style="padding:4px 0; text-align:right; font-weight:500; color:#0f1728; font-family:monospace;">{{ $booking->booking_code }}</td>
            </tr>
        </table>
    </div>

    @if ($booking->payment_note)
    <div style="background:#fef2f2; border:1px solid #fecaca; border-radius:8px; padding:14px 16px; font-size:0.8125rem; color:#991b1b; margin:20px 0;">
        <strong>Note:</strong> {{ $booking->payment_note }}
    </div>
    @endif
    @endif

    @if($booking->guest_email && $booking->guest_tracking_token)
    <div style="text-align:center; margin:28px 0 8px;">
        <a href="{{ $frontendUrl }}/booking/track/{{ $booking->guest_tracking_token }}" style="display:inline-block; background:#004e89; color:#fff; text-decoration:none; padding:12px 28px; border-radius:6px; font-size:0.9375rem; font-weight:600;">Track Your Booking</a>
    </div>
    @elseif(!$booking->guest_email)
    <div style="text-align:center; margin:28px 0 8px;">
        <a href="{{ $frontendUrl }}/bookings?bookingId={{ $booking->id }}" style="display:inline-block; background:#004e89; color:#fff; text-decoration:none; padding:12px 28px; border-radius:6px; font-size:0.9375rem; font-weight:600;">View My Booking</a>
    </div>
    @endif
@endsection

@section('footer')
    This is an automated message from Aktiv. If you have questions, please contact {{ $hub->name }} directly.
@endsection
