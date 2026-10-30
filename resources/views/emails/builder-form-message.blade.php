@extends('emails.layout')
@php
    $rows = [
        'Name' => e($data['name']),
        'Email' => '<a href="mailto:'.e($data['email']).'" style="color:#17a85f;font-weight:600;">'.e($data['email']).'</a>',
        'Phone' => filled($data['phone'] ?? null) ? e($data['phone']) : '—',
        'Page' => filled($data['page'] ?? null) ? '<a href="'.e($data['page']).'" style="color:#17a85f;">'.e(\Illuminate\Support\Str::limit($data['page'], 60)).'</a>' : '—',
        'Received' => now()->format('d M Y, H:i').' UTC',
    ];
@endphp

@section('preheader', $data['name'].': '.\Illuminate\Support\Str::limit($data['message'], 90))
@section('eyebrow', 'New message ✉️')
@section('headline', 'Message from '.$data['name'])
@section('subline', 'Sent with the contact form on '.$form->site_name.'.')

@section('body')
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border:1px solid #e2e8f0;border-radius:14px;">
        @foreach ($rows as $label => $value)
            <tr>
                <td width="100" style="padding:12px 18px;font-size:13px;color:#64748b;{{ $loop->last ? '' : 'border-bottom:1px solid #f1f5f9;' }}">{{ $label }}</td>
                <td style="padding:12px 18px 12px 0;font-size:14px;color:#0f172a;{{ $loop->last ? '' : 'border-bottom:1px solid #f1f5f9;' }}">{!! $value !!}</td>
            </tr>
        @endforeach
    </table>

    <p style="margin:26px 0 10px;font-size:12px;font-weight:600;letter-spacing:1.4px;text-transform:uppercase;color:#64748b;">Message</p>
    <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:14px;padding:18px 20px;color:#1e293b;line-height:1.6;white-space:pre-wrap;">{{ $data['message'] }}</div>

    <p style="margin:22px 0 0;font-size:14px;color:#64748b;">Just reply to this email to answer {{ $data['name'] }}. Owlvix doesn't keep a copy of this message.</p>
@endsection

@section('footer_note', 'You get this because you added an Owlvix contact form to your page. Want a custom website or more features? Reply to any Owlvix email.')
