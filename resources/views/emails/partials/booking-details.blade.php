{{-- The booking's details as a small table, for the booking emails. --}}
@php($s = $b->page->settings)
<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="border:1px solid #e2e8f0;border-radius:12px;">
    @foreach (array_filter([
        'Service' => $b->service.($b->price ? ' · '.$b->price : ''),
        'When' => $b->when(),
        'Time zone' => $b->page->tz(),
        'Where' => $s['location'] ?? '',
        'Name' => $b->name,
        'Email' => $b->email,
        'Phone' => $b->phone,
        'Message' => $b->note,
    ]) as $label => $value)
        <tr><td style="padding:10px 14px;border-bottom:1px solid #f1f5f9;font-size:13px;color:#64748b;width:110px;vertical-align:top;">{{ $label }}</td><td style="padding:10px 14px;border-bottom:1px solid #f1f5f9;font-size:14px;color:#0f172a;">{!! nl2br(e($value)) !!}</td></tr>
    @endforeach
</table>
