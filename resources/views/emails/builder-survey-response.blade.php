@extends('emails.layout')
@php
    $answers = $response->answers ?? [];
    $fields = array_values(array_filter($form->schema['fields'] ?? [], fn ($f) => $f['type'] !== 'heading'));
    $reply = \App\Support\SurveySchema::replyTo($form->schema ?? [], $answers);
@endphp

@section('preheader', 'Someone answered '.$form->site_name)
@section('eyebrow', 'New answer 📬')
@section('headline', $form->site_name)
@section('subline', 'Received '.$response->created_at->format('d M Y, H:i').' UTC.')

@section('body')
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border:1px solid #e2e8f0;border-radius:14px;">
        @foreach ($fields as $f)
            @php($a = \App\Support\SurveySchema::text($answers[$f['id']] ?? ''))
            <tr>
                <td style="padding:12px 18px;{{ $loop->last ? '' : 'border-bottom:1px solid #f1f5f9;' }}">
                    <div style="font-size:12px;color:#64748b;margin-bottom:3px;">{{ $f['label'] }}</div>
                    <div style="font-size:14px;color:#0f172a;white-space:pre-line;">{{ $a !== '' ? $a : '—' }}</div>
                </td>
            </tr>
        @endforeach
    </table>

    <p style="margin:22px 0 0;font-size:14px;color:#64748b;">{{ $reply ? 'Reply to this email to answer '.$reply.'. ' : '' }}All answers are saved in the <a href="{{ route('form-builder') }}" style="color:#17a85f;">form builder</a>, where you can download them as a CSV file.</p>
@endsection

@section('footer_note', 'You get this because you made a form with the free Owlvix form builder. Want a custom website or app? Reply to any Owlvix email.')
