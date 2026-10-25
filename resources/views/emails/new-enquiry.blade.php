@extends('emails.layout')
@php
    $display = "Poppins, 'Segoe UI', Helvetica, Arial, sans-serif";
    $rows = [
        'Email' => '<a href="mailto:'.e($enquiry->email).'" style="color:#17a85f;font-weight:600;">'.e($enquiry->email).'</a>',
        'Needs' => e($enquiry->service ?: '—'),
        'Budget' => e($enquiry->budget ?: '—'),
        'Sent from' => $enquiry->source === 'popup' ? 'Quote popup' : 'Contact form',
        'Received' => $enquiry->created_at->format('d M Y, H:i'),
    ];
@endphp

@section('preheader', e($enquiry->name).' wants: '.e($enquiry->service ?: 'a project').' — '.e(\Illuminate\Support\Str::limit($enquiry->message, 80)))
@section('eyebrow', 'New enquiry 🎉')
@section('headline', e($enquiry->name))
@section('subline', 'Just sent a request from owlvix.com.')

@section('body')
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border:1px solid #e2e8f0;border-radius:14px;">
        @foreach ($rows as $label => $value)
            <tr>
                <td width="110" style="padding:12px 18px;font-size:13px;color:#64748b;{{ $loop->last ? '' : 'border-bottom:1px solid #f1f5f9;' }}">{{ $label }}</td>
                <td style="padding:12px 18px 12px 0;font-size:14px;color:#0f172a;{{ $loop->last ? '' : 'border-bottom:1px solid #f1f5f9;' }}">{!! $value !!}</td>
            </tr>
        @endforeach
    </table>

    <p style="margin:26px 0 10px;font-size:12px;font-weight:600;letter-spacing:1.4px;text-transform:uppercase;color:#64748b;">Message</p>
    <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:14px;padding:18px 20px;white-space:pre-wrap;color:#1e293b;">{{ $enquiry->message }}</div>

    <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:28px 0 0;">
        <tr>
            <td bgcolor="#1ec773" style="border-radius:999px;">
                <a href="{{ route('admin.messages.show', $enquiry) }}#reply" style="display:inline-block;padding:13px 24px;font-family:{{ $display }};font-size:14px;font-weight:600;color:#04130b;border-radius:999px;">Reply in dashboard</a>
            </td>
            <td width="10"></td>
            <td style="border:1.5px solid #cbd5e1;border-radius:999px;">
                <a href="{{ route('admin.messages.index') }}" style="display:inline-block;padding:12px 22px;font-family:{{ $display }};font-size:14px;font-weight:600;color:#0f172a;border-radius:999px;">All enquiries</a>
            </td>
        </tr>
    </table>
@endsection

@section('footer_note', 'Replying to this email goes straight to '.e($enquiry->email).'.')
