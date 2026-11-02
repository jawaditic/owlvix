@extends('emails.layout')
@php($b = $booking)
@php($note = $b->page->settings['note'] ?? '')

@section('preheader', ($reminder ? 'See you soon: ' : 'You’re booked: ').$b->service.', '.$b->when().'.')
@section('eyebrow', $reminder ? 'Reminder' : 'Booking confirmed')
@section('headline', $reminder ? 'See you soon, '.$b->name.'!' : 'You’re booked, '.$b->name.'!')
@section('subline', $b->page->name().' — '.$b->when())

@section('body')
    @include('emails.partials.booking-details', ['b' => $b])
    @if ($note)
        <p style="margin:18px 0 0;font-size:14px;color:#0f172a;">{!! nl2br(e($note)) !!}</p>
    @endif
    <p style="margin:24px 0 0;"><a href="{{ $b->manageUrl() }}" style="display:inline-block;padding:12px 22px;border-radius:999px;background:#17a85f;color:#fff;font-weight:600;text-decoration:none;">See or cancel your booking</a></p>
    <p style="margin:18px 0 0;font-size:14px;color:#64748b;">The calendar file attached adds it to your calendar. Questions? Just reply to this email to reach {{ $b->page->name() }}.</p>
@endsection

@section('footer_note', 'You get this because you booked with '.$b->page->name().' on a booking page made with Owlvix. Please contact the business about your booking.')
