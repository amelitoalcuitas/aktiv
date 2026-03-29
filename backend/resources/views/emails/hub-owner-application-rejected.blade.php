@extends('emails.layout')

@section('title', 'Hub Owner Application Reviewed')

@section('content')
    <div style="text-align:center; margin-bottom:28px;">
        <div style="display:inline-block; width:48px; height:48px; background:#fee2e2; border-radius:50%; line-height:48px; font-size:24px; margin-bottom:12px;">✉️</div>
        <h1 style="margin:0 0 4px; font-size:1.25rem; color:#0f1728;">Your application was reviewed</h1>
        <p style="color:#64748b; font-size:0.875rem; margin:0;">We’re not able to approve your hub owner request at this time.</p>
    </div>

    <p style="font-size:0.875rem; color:#64748b; margin:24px 0; line-height:1.7;">
        Thanks for your interest in becoming a hub owner on Aktiv. After review, your request was not approved at this time.
    </p>

    @if ($hubOwnerRequest->review_notes)
    <div style="background:#fef2f2; border:1px solid #fecaca; border-radius:8px; padding:14px 16px; font-size:0.875rem; color:#991b1b; margin:20px 0;">
        <strong>Review note:</strong> {{ $hubOwnerRequest->review_notes }}
    </div>
    @endif
@endsection

@section('footer')
    This is an automated message from Aktiv.
@endsection
