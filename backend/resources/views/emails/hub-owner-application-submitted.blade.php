@extends('emails.layout')

@section('title', 'New Hub Owner Application')

@section('content')
    <div style="text-align:center; margin-bottom:28px;">
        <div style="display:inline-block; width:48px; height:48px; background:#dbeafe; border-radius:50%; line-height:48px; font-size:24px; margin-bottom:12px;">📬</div>
        <h1 style="margin:0 0 4px; font-size:1.25rem; color:#0f1728;">New hub owner application</h1>
        <p style="color:#64748b; font-size:0.875rem; margin:0;">A user has requested hub owner access.</p>
    </div>

    <div style="background:#f0f4f8; border-radius:8px; padding:16px; margin:24px 0; font-size:0.875rem;">
        <table style="width:100%; border-collapse:collapse;">
            <tr>
                <td style="padding:4px 0; color:#64748b;">Applicant</td>
                <td style="padding:4px 0; text-align:right; font-weight:500; color:#0f1728;">{{ $hubOwnerRequest->user->name }}</td>
            </tr>
            <tr>
                <td style="padding:4px 0; color:#64748b;">Email</td>
                <td style="padding:4px 0; text-align:right; font-weight:500; color:#0f1728;">{{ $hubOwnerRequest->user->email }}</td>
            </tr>
            @if ($hubOwnerRequest->contact_number)
            <tr>
                <td style="padding:4px 0; color:#64748b;">Contact Number</td>
                <td style="padding:4px 0; text-align:right; font-weight:500; color:#0f1728;">{{ $hubOwnerRequest->contact_number }}</td>
            </tr>
            @endif
            @if ($hubOwnerRequest->hub_name)
            <tr>
                <td style="padding:4px 0; color:#64748b;">Hub Name</td>
                <td style="padding:4px 0; text-align:right; font-weight:500; color:#0f1728;">{{ $hubOwnerRequest->hub_name }}</td>
            </tr>
            @endif
            @if ($hubOwnerRequest->city)
            <tr>
                <td style="padding:4px 0; color:#64748b;">City</td>
                <td style="padding:4px 0; text-align:right; font-weight:500; color:#0f1728;">{{ $hubOwnerRequest->city }}</td>
            </tr>
            @endif
            <tr>
                <td style="padding:4px 0; color:#64748b;">Submitted</td>
                <td style="padding:4px 0; text-align:right; font-weight:500; color:#0f1728;">{{ $hubOwnerRequest->created_at->timezone('Asia/Manila')->format('M j, Y g:i A') }}</td>
            </tr>
        </table>
    </div>

    <div style="background:#fff; border:1px solid #dbe4ef; border-radius:8px; padding:16px; margin:20px 0;">
        <p style="margin:0 0 8px; font-size:0.8125rem; color:#64748b; text-transform:uppercase; letter-spacing:0.08em;">Application Message</p>
        <p style="margin:0; font-size:0.875rem; color:#0f1728; line-height:1.7;">{{ $hubOwnerRequest->message }}</p>
    </div>

    <div style="text-align:center; margin:28px 0 8px;">
        <a href="{{ $frontendUrl }}/panel/requests" style="display:inline-block; background:#004e89; color:#fff; text-decoration:none; padding:12px 28px; border-radius:8px; font-size:0.9375rem; font-weight:600;">Review Requests</a>
    </div>
@endsection

@section('footer')
    This is an automated message from Aktiv for super admin review.
@endsection
