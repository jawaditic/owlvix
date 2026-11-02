@extends('emails.layout')
@php($b = $booking)

@section('preheader', $b->name.' booked '.$b->service.' on '.$b->when().'.')
@section('eyebrow', 'New booking')
@section('headline', $b->name.' booked '.$b->service)
@section('subline', $b->when().' ('.$b->page->tz().')')

@section('body')
    @include('emails.partials.booking-details', ['b' => $b])
    <p style="margin:24px 0 0;"><a href="{{ route('booking-page-builder.editor') }}" style="display:inline-block;padding:12px 22px;border-radius:999px;background:#17a85f;color:#fff;font-weight:600;text-decoration:none;">See all bookings</a></p>
    <p style="margin:18px 0 0;font-size:14px;color:#64748b;">Reply to this email to answer {{ $b->name }}. They got a confirmation with a link to cancel.</p>
@endsection

@section('footer_note', 'You get this because someone booked on your booking page '.$b->page->publicUrl().', made with the Owlvix booking page builder.')
