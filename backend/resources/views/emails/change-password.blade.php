@extends('emails.layout')

@section('title', 'Change Your Password — Aktiv')

@section('content')
    <div style="text-align:center; margin-bottom:28px;">
        <div style="display:inline-block; width:48px; height:48px; background:#dbeafe; border-radius:50%; line-height:48px; font-size:24px; margin-bottom:12px;">🔑</div>
        <h1 style="margin:0 0 4px; font-size:1.25rem; color:#0f1728;">Change Your Password</h1>
        <p style="color:#64748b; font-size:0.875rem; margin:0;">Hi <strong>{{ $name }}</strong></p>
    </div>

    <p style="font-size:0.875rem; color:#64748b; margin:24px 0; line-height:1.6;">
        You requested a password change from your account security settings. Click the button below to choose a new password.
    </p>

    <div style="text-align:center; margin:28px 0;">
        <a href="{{ $resetUrl }}" style="display:inline-block; background:#004e89; color:#fff; text-decoration:none; font-size:0.9375rem; font-weight:600; padding:12px 32px; border-radius:8px;">Change Password</a>
    </div>

    <div style="background:#f0f7ff; border:1px solid #bfdbfe; border-radius:8px; padding:14px 16px; font-size:0.8125rem; color:#1e40af; margin:20px 0;">
        Click the button above to set your new password. If the button doesn't work, copy and paste the link into your browser.
    </div>

    <p style="font-size:0.8125rem; color:#64748b; margin:16px 0; text-align:center;">This link expires in 60 minutes.</p>
@endsection

@section('footer')
    If you did not request this, your account security settings may have been accessed without your permission. We recommend logging in and reviewing your account immediately.<br>
    This is an automated message from Aktiv.
@endsection
