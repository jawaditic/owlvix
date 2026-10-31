@extends('emails.layout')
@php($done = $build->status === 'done')

@section('preheader', $done ? 'Your Android app is ready: download it and put it on Google Play.' : 'Your app build didn’t finish.')
@section('eyebrow', $done ? 'Your app is ready 📱' : 'Build didn’t finish')
@section('headline', $app->name.' '.$build->version_name)
@section('subline', $done ? 'Android app, version '.$build->version_name.' (build '.$build->version_code.').' : ($build->error ?: 'Please try again.'))

@section('body')
    @if ($done)
        <p style="margin:0 0 16px;font-size:15px;color:#0f172a;">Two files were made for you. The links work for 7 days — you can always get new ones in the <a href="{{ route('app-builder') }}" style="color:#17a85f;">app builder</a>.</p>
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border:1px solid #e2e8f0;border-radius:14px;">
            <tr><td style="padding:14px 18px;border-bottom:1px solid #f1f5f9;">
                <div style="font-size:14px;color:#0f172a;font-weight:700;">Try it on your phone — APK</div>
                <div style="font-size:13px;color:#64748b;margin:3px 0 8px;">Open this link on an Android phone and install it. Your phone may ask you to allow installs from your browser.</div>
                <a href="{{ $build->downloadUrl('apk') }}" style="color:#17a85f;font-weight:700;">Download the APK</a>
            </td></tr>
            <tr><td style="padding:14px 18px;">
                <div style="font-size:14px;color:#0f172a;font-weight:700;">For Google Play — AAB</div>
                <div style="font-size:13px;color:#64748b;margin:3px 0 8px;">Upload this file in Google Play Console → your app → Production (or Internal testing) → Create new release.</div>
                <a href="{{ $build->downloadUrl('aab') }}" style="color:#17a85f;font-weight:700;">Download the AAB</a>
            </td></tr>
        </table>
        <p style="margin:22px 0 6px;font-size:14px;color:#0f172a;font-weight:700;">Putting it on Google Play</p>
        <ol style="margin:0;padding-left:20px;font-size:14px;color:#334155;line-height:1.6;">
            <li>Make a Google Play developer account (a one-time $25 fee) at play.google.com/console.</li>
            <li>Create an app, fill in the store listing (description, screenshots, privacy policy link) and the content questions.</li>
            <li>Upload the AAB file as a new release and send it for review.</li>
        </ol>
        <p style="margin:16px 0 0;font-size:14px;color:#64748b;">Keep a copy of your signing key (in the app builder: “Download signing key”). Every update must be signed with the same key. Want it on the iPhone App Store too? Reply to this email — we can publish it for you.</p>
    @else
        <p style="margin:0;font-size:15px;color:#0f172a;">{{ $build->error ?: 'The build didn’t finish.' }} Open the <a href="{{ route('app-builder') }}" style="color:#17a85f;">app builder</a> and press Build again. If it keeps happening, reply to this email and we’ll look into it.</p>
    @endif
@endsection

@section('footer_note', 'You get this because you built an app with the free Owlvix mobile app builder.')
