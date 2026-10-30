@extends('emails.layout')
@php($host = parse_url($url, PHP_URL_HOST))

@section('preheader', 'We are preparing a full SEO audit of '.$host.'. Expect it within 2 business days.')
@section('eyebrow', 'Audit requested ✓')
@section('headline', 'Thanks, '.($lead->firstName()).'! Your full audit is on its way.')
@section('subline', 'We are auditing '.($host).' and will email your report within 2 business days.')
@section('owl', '1')

@section('body')
    <p style="margin:0 0 16px;">Your free check gave you a quick score for one page. The full audit goes much deeper — our full SEO audit reviews <strong>{{ $host }}</strong> the way an agency would:</p>
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border:1px solid #e2e8f0;border-radius:14px;">
        @foreach ([
            ['🔎', 'Whole-site technical SEO', 'crawling, indexing, duplicate and orphan pages, sitemap and redirects'],
            ['✍️', 'Content quality', 'expertise and trust signals Google and AI look for, thin pages, readability'],
            ['📈', 'Search intent', 'what actually ranks for your keywords and whether your pages match'],
            ['⚡', 'Speed & Core Web Vitals', 'what slows your pages down and how to fix it'],
            ['🤖', 'AI search readiness', 'how likely ChatGPT, Perplexity and Google AI are to cite you'],
            ['🧩', 'Schema & action plan', 'structured data fixes and a prioritised to-do list'],
        ] as [$icon, $title, $text])
            <tr><td style="padding:12px 16px;border-bottom:1px solid #f1f5f9;font-size:14px;"><span style="font-size:16px;">{{ $icon }}</span>&nbsp; <strong style="color:#0f172a;">{{ $title }}</strong> — <span style="color:#475569;">{{ $text }}</span></td></tr>
        @endforeach
    </table>
    <p style="margin:22px 0 0;">You will get it as a PDF report with clear priorities. If you want, we can then walk you through it and fix the issues — every project <strong>starts free</strong>, and you pay per milestone only after you approve the work.</p>
    <p style="margin:16px 0 0;font-size:14px;color:#64748b;">Anything specific you want us to look at? Just reply to this email.</p>
@endsection
