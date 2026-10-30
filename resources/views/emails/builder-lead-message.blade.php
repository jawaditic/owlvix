@extends('emails.layout')
@php
    $who = $lead->name ?: $lead->email;
    $rows = [
        'Name' => filled($lead->name) ? e($lead->name) : '—',
        'Email' => '<a href="mailto:'.e($lead->email).'" style="color:#17a85f;font-weight:600;">'.e($lead->email).'</a>',
        'Phone' => filled($lead->phone) ? e($lead->phone) : '—',
        'Step' => filled($lead->step) ? e($lead->step).($lead->variant ? ' (version '.strtoupper(e($lead->variant)).')' : '') : '—',
        'Page' => filled($page) ? '<a href="'.e($page).'" style="color:#17a85f;">'.e(\Illuminate\Support\Str::limit($page, 60)).'</a>' : '—',
        'Received' => $lead->created_at->format('d M Y, H:i').' UTC',
    ];
@endphp

@section('preheader', $who.' signed up on '.$form->site_name)
@section('eyebrow', 'New lead 🎉')
@section('headline', 'New lead: '.$who)
@section('subline', 'Sent with the sign-up form on '.$form->site_name.'.')

@section('body')
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border:1px solid #e2e8f0;border-radius:14px;">
        @foreach ($rows as $label => $value)
            <tr>
                <td width="100" style="padding:12px 18px;font-size:13px;color:#64748b;{{ $loop->last ? '' : 'border-bottom:1px solid #f1f5f9;' }}">{{ $label }}</td>
                <td style="padding:12px 18px 12px 0;font-size:14px;color:#0f172a;{{ $loop->last ? '' : 'border-bottom:1px solid #f1f5f9;' }}">{!! $value !!}</td>
            </tr>
        @endforeach
    </table>

    <p style="margin:22px 0 0;font-size:14px;color:#64748b;">Just reply to this email to contact {{ $who }}. All your leads are saved in the <a href="{{ route('landing-builder') }}" style="color:#17a85f;">page builder</a>, where you can download them as a CSV file.</p>
@endsection

@section('footer_note', 'You get this because you added an Owlvix sign-up form to your funnel. Want a custom website or more features? Reply to any Owlvix email.')
