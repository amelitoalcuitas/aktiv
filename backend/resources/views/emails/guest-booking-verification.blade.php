@extends('emails.layout')

@section('title', 'Booking Verification Code — Aktiv')

@section('content')
    <p style="font-size:0.875rem; color:#64748b; line-height:1.6; margin:0 0 8px;">Hi there,</p>
    <p style="font-size:0.875rem; color:#64748b; line-height:1.6; margin:0 0 8px;">You requested to book a court at <strong>{{ $hubName }}</strong>. Use the code below to verify your email and complete your booking:</p>

    <div style="font-size:2.5rem; font-weight:700; letter-spacing:0.3em; color:#004e89; text-align:center; margin:24px 0;">{{ $code }}</div>

    <p style="font-size:0.875rem; color:#64748b; line-height:1.6; margin:0 0 8px;">This code expires in <strong>10 minutes</strong>.</p>

    <p style="font-size:0.875rem; color:#64748b; margin-top:24px; line-height:1.6;">
        Your email is required to verify your identity and prevent duplicate or spam bookings.
        If you did not request this, you can safely ignore this email.
    </p>
@endsection
