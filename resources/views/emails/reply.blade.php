@extends('emails.layout')

@section('preheader', e(\Illuminate\Support\Str::limit(preg_replace('/\s+/', ' ', $reply->body), 110)))
@section('eyebrow', 'Message from Owlvix')
@section('headline', 'About your '.e($enquiry->service ? strtolower($enquiry->service) : 'project').' request')
@section('subline', 'A personal reply from our team.')

@section('body')
    <div style="white-space:pre-wrap;font-size:15px;line-height:1.7;color:#1e293b;">{{ $reply->body }}</div>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:30px 0 0;">
        <tr>
            <td width="52" valign="middle"><img src="{{ asset('apple-touch-icon.png') }}" width="42" height="42" alt="" style="display:block;border-radius:12px;border:0;"></td>
            <td valign="middle" style="font-size:14px;line-height:1.4;">
                <strong style="color:#0f172a;">The Owlvix team</strong><br>
                <a href="{{ url('/') }}" style="color:#17a85f;">owlvix.com</a>
            </td>
        </tr>
    </table>

    <p style="margin:30px 0 8px;font-size:12px;font-weight:600;letter-spacing:1.4px;text-transform:uppercase;color:#94a3b8;">Your original message · {{ $enquiry->created_at->format('d M Y') }}</p>
    <div style="border-left:3px solid #e2e8f0;padding:2px 0 2px 14px;color:#64748b;font-size:14px;white-space:pre-wrap;">{{ $enquiry->message }}</div>
@endsection

@section('footer_note', 'Just reply to this email to continue the conversation.')
