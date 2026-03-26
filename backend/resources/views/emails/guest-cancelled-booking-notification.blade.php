@extends('emails.layout')

@section('title', 'Booking Cancelled — Aktiv')

@section('content')
    <style>
        .header { text-align: center; margin-bottom: 28px; }
        .icon-circle { display: inline-block; width: 48px; height: 48px; background: #fee2e2; border-radius: 50%; line-height: 48px; font-size: 24px; margin-bottom: 12px; }
        h1 { margin: 0 0 4px; font-size: 1.25rem; color: #0f1728; }
        .subtitle { color: #64748b; font-size: 0.875rem; margin: 0; }
        .details { background: #f0f4f8; border-radius: 8px; padding: 16px; margin: 24px 0; font-size: 0.875rem; }
        .details table { width: 100%; border-collapse: collapse; }
        .details td { padding: 4px 0; color: #64748b; }
        .details td:last-child { text-align: right; font-weight: 500; color: #0f1728; }
        .code { font-size: 1.5rem; font-weight: 700; letter-spacing: 0.2em; color: #0f1728; margin: 4px 0; font-family: monospace; }
        .btn-wrap { text-align: center; margin: 28px 0 8px; }
        .btn { display: inline-block; background: #004e89; color: #fff; text-decoration: none; padding: 12px 28px; border-radius: 6px; font-size: 0.9375rem; font-weight: 600; }
    </style>

    <div class="header">
        <div class="icon-circle">❌</div>
        <h1>Booking Cancelled</h1>
        <p class="subtitle">
            <strong>{{ $booking->guest_name ?? $booking->bookedBy?->name ?? 'A guest' }}</strong>
            cancelled their booking at <strong>{{ $hub->name }}</strong>.
        </p>
    </div>

    <div class="details">
        <table>
            <tr>
                <td>Customer</td>
                <td>{{ $booking->guest_name ?? $booking->bookedBy?->name ?? 'Guest' }}</td>
            </tr>
            @if ($booking->bookedBy?->email ?? $booking->guest_email)
            <tr>
                <td>Email</td>
                <td>{{ $booking->bookedBy?->email ?? $booking->guest_email }}</td>
            </tr>
            @endif
            <tr>
                <td>Court</td>
                <td>{{ $courtName }}</td>
            </tr>
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
            <tr>
                <td>Booking Code</td>
                <td><span class="code">{{ $booking->booking_code }}</span></td>
            </tr>
        </table>
    </div>

    <div class="btn-wrap">
        <a href="{{ $frontendUrl }}/dashboard/bookings?hubId={{ $hub->id }}&bookingId={{ $booking->id }}" class="btn">View in Dashboard</a>
    </div>
@endsection
