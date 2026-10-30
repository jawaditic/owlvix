@extends('emails.layout')
@php
    $r = $report;
    $host = parse_url($r['final_url'], PHP_URL_HOST);
    $col = ['good' => '#16a34a', 'average' => '#d97706', 'poor' => '#dc2626'][$r['rating']];
    $issues = array_values(array_filter($r['checks'], fn ($c) => $c['status'] !== 'pass'));
    $passed = array_values(array_filter($r['checks'], fn ($c) => $c['status'] === 'pass'));
    $badge = ['fail' => ['Fix first', '#dc2626', '#fee2e2'], 'warn' => ['Improve', '#b45309', '#fef3c7']];
@endphp

@section('preheader', 'Your SEO score is '.$r['score'].'/100 — here is what to fix first on '.$host.'.')
@section('eyebrow', 'Free SEO check ✓')
@section('headline', ($name ? 'Hi '.(strtok($name, ' ')).', h' : 'H').'ere are your SEO tips')
@section('subline', 'For '.($host).' — checked '.\Illuminate\Support\Carbon::parse($r['checked_at'])->format('j M Y'))

@section('body')
    {{-- Score --}}
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border:1px solid #e2e8f0;border-radius:14px;">
        <tr>
            <td width="110" align="center" style="padding:20px 0 20px 18px;">
                <div style="width:86px;height:86px;border-radius:50%;border:7px solid {{ $col }};text-align:center;">
                    <div style="font-family:Poppins,Arial,sans-serif;font-size:30px;font-weight:700;color:{{ $col }};line-height:1;padding-top:20px;">{{ $r['score'] }}</div>
                    <div style="font-size:11px;color:#64748b;">of 100</div>
                </div>
            </td>
            <td style="padding:20px 20px 20px 14px;">
                <p style="margin:0 0 6px;font-size:15px;color:#1e293b;">{{ $r['summary'] }}</p>
                <p style="margin:0;font-size:13px;color:#64748b;">
                    <span style="color:#dc2626;font-weight:600;">{{ $r['counts']['fail'] }} to fix first</span> ·
                    <span style="color:#b45309;font-weight:600;">{{ $r['counts']['warn'] }} to improve</span> ·
                    <span style="color:#16a34a;font-weight:600;">{{ $r['counts']['pass'] }} passed</span>
                </p>
            </td>
        </tr>
    </table>

    {{-- Tips --}}
    @if ($issues)
        <p style="margin:30px 0 12px;font-size:12px;font-weight:600;letter-spacing:1.4px;text-transform:uppercase;color:#64748b;">Your tips, most important first</p>
        @foreach ($issues as $i => $c)
            @php([$bl, $bc, $bb] = $badge[$c['status']])
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border:1px solid #e2e8f0;border-radius:12px;margin-bottom:10px;">
                <tr>
                    <td width="40" valign="top" style="padding:14px 0 14px 14px;">
                        <div style="width:26px;height:26px;border-radius:8px;background:#12b76a;color:#ffffff;font-weight:700;font-size:13px;text-align:center;line-height:26px;">{{ $i + 1 }}</div>
                    </td>
                    <td style="padding:14px 16px 14px 10px;">
                        <p style="margin:0;font-weight:600;color:#0f172a;">{{ $c['title'] }}
                            <span style="display:inline-block;margin-left:6px;padding:1px 8px;border-radius:9px;font-size:11px;font-weight:700;color:{{ $bc }};background:{{ $bb }};">{{ $bl }}</span></p>
                        <p style="margin:4px 0 0;font-size:13px;color:#64748b;">Found: {{ $c['found'] }}</p>
                        <p style="margin:6px 0 0;font-size:14px;color:#1e293b;">💡 {{ $c['tip'] }}</p>
                    </td>
                </tr>
            </table>
        @endforeach
    @endif

    @if ($passed)
        <p style="margin:24px 0 8px;font-size:12px;font-weight:600;letter-spacing:1.4px;text-transform:uppercase;color:#64748b;">Already good ✓</p>
        <p style="margin:0;font-size:14px;color:#16a34a;">{{ implode(' · ', array_column($passed, 'title')) }}</p>
    @endif

    {{-- Offer --}}
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-top:28px;background:#0b1324;border-radius:14px;">
        <tr><td style="padding:22px 24px;color:#e2e8f0;font-size:14px;">
            <p style="margin:0 0 8px;font-family:Poppins,Arial,sans-serif;font-size:18px;font-weight:700;color:#ffffff;">Want us to fix these for you?</p>
            <p style="margin:0 0 16px;">We make these fixes directly in your site — WordPress, Shopify, Laravel or custom. <strong style="color:#34e08f;">Start free:</strong> we plan the work at no cost, then you pay per milestone, only after you approve it.</p>
            <a href="{{ route('service.seo-services') }}#contact" style="display:inline-block;background:#12b76a;color:#04130b;font-weight:700;padding:11px 18px;border-radius:10px;">Get a free quote →</a>
            <span style="margin-left:12px;font-size:13px;"><a href="{{ route('speed-test') }}?url={{ urlencode($r['url']) }}" style="color:#93c5fd;">Run a free speed test too</a></span>
        </td></tr>
    </table>
    <p style="margin:18px 0 0;font-size:12px;color:#94a3b8;">You asked for this report at {{ route('seo-checker') }}. Just reply to this email if you have questions.</p>
@endsection
