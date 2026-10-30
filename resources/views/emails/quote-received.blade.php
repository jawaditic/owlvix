@extends('emails.layout')
@php
    $font = "Inter, 'Segoe UI', Helvetica, Arial, sans-serif";
    $display = "Poppins, 'Segoe UI', Helvetica, Arial, sans-serif";
    $thanksUrl = URL::signedRoute('thanks', $enquiry);
@endphp

@section('preheader', 'We got your request and will reply within one business day. Our owl recorded a quick message for you.')
@section('eyebrow', 'Request received ✓')
@section('headline', 'Thanks, '.e($enquiry->firstName()).'! We\'re on it.')
@section('subline', 'Your request is with our team. Expect a reply within one business day.')

@section('body')
    {{-- Owl video card --}}
    <a href="{{ $thanksUrl }}" style="display:block;">
        <img src="{{ asset('images/email-owl-video.png') }}" width="528" alt="▶ Play a message from the Owlvix owl" style="display:block;width:100%;max-width:528px;height:auto;border:0;border-radius:14px;">
    </a>
    <p style="margin:12px 0 0;text-align:center;font-size:14px;">
        <a href="{{ $thanksUrl }}" style="color:#17a85f;font-weight:600;">▶ Hear a message from our owl</a>
        <span style="color:#94a3b8;"> · 15 seconds</span>
    </p>

    {{-- Their request --}}
    <p style="margin:32px 0 12px;font-size:12px;font-weight:600;letter-spacing:1.4px;text-transform:uppercase;color:#64748b;">Your request</p>
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border:1px solid #e2e8f0;border-radius:14px;">
        <tr>
            <td style="padding:18px 20px;">
                @if ($enquiry->service)
                    <span style="display:inline-block;margin:0 6px 10px 0;padding:4px 12px;border-radius:999px;background:#dcfce7;color:#166534;font-size:12px;font-weight:600;">{{ $enquiry->service }}</span>
                @endif
                @if ($enquiry->budget && $enquiry->budget !== 'Not sure yet')
                    <span style="display:inline-block;margin:0 6px 10px 0;padding:4px 12px;border-radius:999px;background:#e0ecff;color:#1e40af;font-size:12px;font-weight:600;">{{ $enquiry->budget }}</span>
                @endif
                <div style="border-left:3px solid #1ec773;padding:2px 0 2px 14px;color:#334155;line-height:1.6;">{!! $enquiry->messageHtml() !!}</div>
            </td>
        </tr>
    </table>

    {{-- Timeline --}}
    <p style="margin:32px 0 14px;font-size:12px;font-weight:600;letter-spacing:1.4px;text-transform:uppercase;color:#64748b;">What happens next</p>
    @foreach ([
        ['We review your request', 'Today — we read it carefully and think about the best way to build it.'],
        ['You get questions or a quote', 'Within one business day, with a clear price and timeline.'],
        ['We start building', 'Weekly updates and a live preview, so there are no surprises.'],
    ] as $i => [$title, $text])
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-bottom:{{ $loop->last ? 0 : 14 }}px;">
            <tr>
                <td width="40" valign="top">
                    <div style="width:30px;height:30px;line-height:30px;border-radius:50%;text-align:center;font-family:{{ $display }};font-weight:700;font-size:14px;{{ $i === 0 ? 'background:#1ec773;color:#04130b;' : 'background:#f1f5f9;color:#475569;' }}">{{ $i + 1 }}</div>
                </td>
                <td valign="top" style="padding-top:4px;">
                    <p style="margin:0;font-weight:600;color:#0f172a;">{{ $title }}</p>
                    <p style="margin:2px 0 0;font-size:14px;color:#64748b;">{{ $text }}</p>
                </td>
            </tr>
        </table>
    @endforeach

    {{-- What clients say (config/testimonials.php) --}}
    @php($t = \App\Support\Testimonials::data())
    @if (count($t['email']))
    <p style="margin:32px 0 14px;font-size:12px;font-weight:600;letter-spacing:1.4px;text-transform:uppercase;color:#64748b;">What clients say</p>
    @foreach (collect($t['reviews'])->whereIn('name', $t['email']) as $r)
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-bottom:12px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:14px;">
            <tr>
                <td style="padding:16px 20px;">
                    <p style="margin:0 0 6px;color:#f59e0b;font-size:14px;letter-spacing:2px;">{{ str_repeat('★', $r['rating']) }}</p>
                    <p style="margin:0 0 10px;color:#334155;font-size:15px;line-height:1.6;">“{{ $r['text'] }}”</p>
                    <p style="margin:0;font-size:13px;color:#64748b;"><strong style="color:#0f172a;">{{ $r['name'] }}</strong>@if ($r['country']) · {{ $r['flag'] }} {{ $r['country'] }}@endif</p>
                </td>
            </tr>
        </table>
    @endforeach
    <p style="margin:6px 0 0;font-size:14px;color:#64748b;">
        @if ($t['five_star'] > 0)<strong style="color:#0f172a;">{{ $t['five_star'] }} of {{ $t['count'] }}</strong> reviews are 5 ★ on Fiverr @else <strong style="color:#0f172a;">{{ $t['count'] }}</strong> reviews on Fiverr @endif ·
        <a href="{{ $t['profile_url'] }}" style="color:#17a85f;font-weight:600;">Read more reviews →</a>
    </p>
    @endif

    {{-- CTA --}}
    <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:32px 0 0;">
        <tr>
            <td bgcolor="#1ec773" style="border-radius:999px;">
                <a href="{{ url('/') }}#services" style="display:inline-block;padding:14px 28px;font-family:{{ $display }};font-size:15px;font-weight:600;color:#04130b;border-radius:999px;">See what we build →</a>
            </td>
        </tr>
    </table>

    <p style="margin:30px 0 0;">Talk soon,<br><strong>The Owlvix team</strong> 🦉</p>
@endsection

@section('footer_note', 'Want to add details or send files? Just reply to this email, it comes straight to our team.')
