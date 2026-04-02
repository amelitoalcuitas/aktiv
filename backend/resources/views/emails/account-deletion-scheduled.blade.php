@extends('emails.layout')

@section('title', 'Account Deletion Scheduled — Aktiv')

@section('content')
    <div style="text-align:center; margin-bottom:28px;">
        <div style="display:inline-block; width:48px; height:48px; background:#fee2e2; border-radius:50%; line-height:48px; font-size:24px; margin-bottom:12px;">⚠</div>
        <h1 style="margin:0 0 4px; font-size:1.25rem; color:#0f1728;">Your account is scheduled for deletion</h1>
        <p style="color:#64748b; font-size:0.875rem; margin:0;">Hi {{ $user->first_name }}, we received a request to delete your Aktiv account.</p>
    </div>

    <div style="background:#f0f4f8; border-radius:8px; padding:16px; margin:24px 0; font-size:0.875rem;">
        <table style="width:100%; border-collapse:collapse;">
            <tr>
                <td style="padding:4px 0; color:#64748b;">Account</td>
                <td style="padding:4px 0; text-align:right; font-weight:500; color:#0f1728;">{{ $user->email }}</td>
            </tr>
            <tr>
                <td style="padding:4px 0; color:#64748b;">Scheduled for</td>
                <td style="padding:4px 0; text-align:right; font-weight:500; color:#dc2626;">
                    {{ \Carbon\Carbon::parse($user->deletion_scheduled_at)->timezone(config('app.timezone'))->isoFormat('ddd, MMM D, YYYY') }} UTC
                </td>
            </tr>
        </table>
    </div>

    <div style="background:#f0f7ff; border:1px solid #bfdbfe; border-radius:8px; padding:14px 16px; font-size:0.8125rem; color:#1e40af; margin:20px 0; line-height:1.6;">
        Changed your mind? You have <strong>30 days</strong> to restore your account before it is permanently deleted. Simply <strong>log back in</strong> and we'll ask if you'd like to keep your account.
    </div>

    <div style="text-align:center; margin:28px 0 8px;">
        <a href="{{ $frontendUrl }}/auth/login" style="display:inline-block; background:#004e89; color:#fff; text-decoration:none; padding:12px 28px; border-radius:6px; font-size:0.9375rem; font-weight:600;">Log Back In to Restore</a>
    </div>
@endsection

@section('footer')
    If you did not request account deletion, please log in immediately and restore your account.<br>
    This is an automated message from Aktiv.
@endsection
