@extends('emails.layout')

@section('preheader', 'Your sign-in code is '.$code.'. It works for 15 minutes.')
@section('eyebrow', 'Free page builder')
@section('headline', 'Your sign-in code')
@section('subline', 'Enter this code in the Owlvix landing page builder to sign in.')

@section('body')
    <p style="margin:0 0 20px;text-align:center;">
        <span style="display:inline-block;padding:16px 28px;background:#f1f5f9;border:1px solid #e2e8f0;border-radius:14px;font-family:Poppins,'Segoe UI',Helvetica,Arial,sans-serif;font-size:34px;font-weight:700;letter-spacing:10px;color:#0f172a;">{{ $code }}</span>
    </p>
    <p style="margin:0 0 12px;">The code works for 15 minutes. Signing in lets you publish your pages on owlvix.com and get messages from your contact form by email.</p>
    <p style="margin:0;font-size:14px;color:#64748b;">Didn't ask for this? You can ignore this email — nobody can sign in without the code.</p>
@endsection
