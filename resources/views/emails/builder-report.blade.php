@extends('emails.layout')

@section('preheader', 'A visitor reported /p/'.$site->slug.': '.\Illuminate\Support\Str::limit($reason, 80))
@section('eyebrow', 'Page reported ⚠️')
@section('headline', '/p/'.$site->slug)
@section('subline', $site->name.' · published by '.$site->account->email)

@section('body')
    <p style="margin:0 0 10px;font-size:12px;font-weight:600;letter-spacing:1.4px;text-transform:uppercase;color:#64748b;">Reason</p>
    <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:14px;padding:18px 20px;color:#1e293b;line-height:1.6;white-space:pre-wrap;">{{ $reason }}</div>
    <p style="margin:18px 0 0;font-size:14px;color:#64748b;">Reported by: {{ $email ?: 'anonymous' }} · Reports for this page: {{ $site->reports }}</p>

    <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:26px 0 0;">
        <tr>
            <td bgcolor="#1ec773" style="border-radius:999px;">
                <a href="{{ route('admin.builder.index') }}" style="display:inline-block;padding:13px 24px;font-family:Poppins,'Segoe UI',Helvetica,Arial,sans-serif;font-size:14px;font-weight:600;color:#04130b;border-radius:999px;">Review in dashboard</a>
            </td>
            <td width="10"></td>
            <td style="border:1.5px solid #cbd5e1;border-radius:999px;">
                <a href="{{ $site->url() }}" style="display:inline-block;padding:12px 22px;font-family:Poppins,'Segoe UI',Helvetica,Arial,sans-serif;font-size:14px;font-weight:600;color:#0f172a;border-radius:999px;">Open the page</a>
            </td>
        </tr>
    </table>
@endsection
