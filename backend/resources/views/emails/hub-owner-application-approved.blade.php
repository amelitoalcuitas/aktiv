@extends('emails.layout')

@section('title', 'Hub Owner Application Approved')

@section('content')
    <div style="text-align:center; margin-bottom:28px;">
        <div style="display:inline-block; width:48px; height:48px; background:#dcfce7; border-radius:50%; line-height:48px; font-size:24px; margin-bottom:12px;">🎉</div>
        <h1 style="margin:0 0 4px; font-size:1.25rem; color:#0f1728;">Your application was approved</h1>
        <p style="color:#64748b; font-size:0.875rem; margin:0;">You can now start setting up your first hub on Aktiv.</p>
    </div>

    <p style="font-size:0.875rem; color:#64748b; margin:24px 0; line-height:1.7;">
        Great news, <strong>{{ $hubOwnerRequest->user->name }}</strong>. Your request for hub owner access has been approved and your account now has dashboard access.
    </p>

    <div style="text-align:center; margin:28px 0;">
        <a href="{{ $frontendUrl }}/dashboard" style="display:inline-block; background:#004e89; color:#fff; text-decoration:none; font-size:0.9375rem; font-weight:600; padding:12px 32px; border-radius:8px;">Open My Dashboard</a>
    </div>

    <div style="background:#f0f7ff; border:1px solid #bfdbfe; border-radius:8px; padding:16px; margin:20px 0;">
        <p style="margin:0 0 10px; font-size:0.875rem; font-weight:700; color:#0f1728;">How to create your first hub</p>
        <ol style="margin:0; padding-left:18px; color:#1e3a5f; font-size:0.875rem; line-height:1.8;">
            <li>Open your dashboard.</li>
            <li>Create your first hub.</li>
            <li>Fill in the basic info, location, operating hours, and media.</li>
            <li>Finish setup by adding courts and booking details.</li>
        </ol>
    </div>
@endsection

@section('footer')
    This is an automated message from Aktiv. Welcome aboard.
@endsection
