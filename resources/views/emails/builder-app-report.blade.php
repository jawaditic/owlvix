@extends('emails.layout')

@section('preheader', 'Someone reported the app '.$app->name.': '.\Illuminate\Support\Str::limit($reason, 80))
@section('eyebrow', 'App reported ⚠️')
@section('headline', $app->name)
@section('subline', $app->package.' · made by '.$app->account->email)

@section('body')
    <p style="margin:0 0 10px;font-size:12px;font-weight:600;letter-spacing:1.4px;text-transform:uppercase;color:#64748b;">Reason</p>
    <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:14px;padding:18px 20px;color:#1e293b;line-height:1.6;white-space:pre-wrap;">{{ $reason }}</div>
    <p style="margin:18px 0 0;font-size:14px;color:#64748b;">Reported by: {{ $email ?: 'anonymous' }} · Reports for this app: {{ $app->reports }}</p>

    <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:26px 0 0;">
        <tr>
            <td bgcolor="#1ec773" style="border-radius:999px;">
                <a href="{{ route('admin.builder.index') }}#apps" style="display:inline-block;padding:13px 24px;font-family:Poppins,'Segoe UI',Helvetica,Arial,sans-serif;font-size:14px;font-weight:600;color:#04130b;border-radius:999px;">Review in dashboard</a>
            </td>
        </tr>
    </table>
@endsection
