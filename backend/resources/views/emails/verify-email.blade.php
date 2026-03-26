@extends('emails.layout')

@section('title', 'Verify Your Email Address — Aktiv')

@section('content')
    <style>
        .header { text-align: center; margin-bottom: 28px; }
        .icon-circle { display: inline-block; width: 48px; height: 48px; background: #dcfce7; border-radius: 50%; line-height: 48px; font-size: 24px; margin-bottom: 12px; }
        h1 { margin: 0 0 4px; font-size: 1.25rem; color: #0f1728; }
        .subtitle { color: #64748b; font-size: 0.875rem; margin: 0; }
        .body-text { font-size: 0.875rem; color: #64748b; margin: 24px 0; line-height: 1.6; }
        .btn-wrap { text-align: center; margin: 28px 0; }
        .btn { display: inline-block; background: #004e89; color: #fff; text-decoration: none; font-size: 0.9375rem; font-weight: 600; padding: 12px 32px; border-radius: 8px; }
        .instructions { background: #f0f7ff; border: 1px solid #bfdbfe; border-radius: 8px; padding: 14px 16px; font-size: 0.8125rem; color: #1e40af; margin: 20px 0; }
        .expiry { font-size: 0.8125rem; color: #64748b; margin: 16px 0; text-align: center; }
    </style>

    <div class="header">
        <div class="icon-circle">✉</div>
        <h1>Verify Your Email</h1>
        <p class="subtitle">Hi <strong>{{ $name }}</strong>, welcome to Aktiv!</p>
    </div>

    <p class="body-text">
        Thanks for signing up! Please verify your email address to activate your account and start booking courts.
    </p>

    <div class="btn-wrap">
        <a href="{{ $verificationUrl }}" class="btn">Verify Email Address</a>
    </div>

    <div class="instructions">
        Click the button above to confirm your email. If the button doesn't work, copy and paste the link into your browser.
    </div>

    <p class="expiry">This link expires in 60 minutes.</p>
@endsection

@section('footer')
    If you did not create an account, no further action is required.<br>
    This is an automated message from Aktiv.
@endsection
