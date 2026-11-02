@extends('emails.layout')
@php($b = $booking)

@if ($recipient === 'client')
@section('preheader', $b->page->name().' cancelled your booking for '.$b->when().'.')
@section('eyebrow', 'Booking cancelled')
@section('headline', 'Your booking was cancelled')
@section('subline', $b->page->name().' cancelled '.$b->service.' on '.$b->when().'.')
@section('body')
    @include('emails.partials.booking-details', ['b' => $b])
    <p style="margin:24px 0 0;"><a href="{{ $b->page->publicUrl() }}" style="display:inline-block;padding:12px 22px;border-radius:999px;background:#17a85f;color:#fff;font-weight:600;text-decoration:none;">Book another time</a></p>
    <p style="margin:18px 0 0;font-size:14px;color:#64748b;">Questions? Just reply to this email to reach {{ $b->page->name() }}.</p>
@endsection
@section('footer_note', 'You get this because you booked with '.$b->page->name().' on a booking page made with Owlvix.')
@else
@section('preheader', $b->name.' cancelled '.$b->service.' on '.$b->when().'.')
@section('eyebrow', 'Booking cancelled')
@section('headline', $b->name.' cancelled')
@section('subline', $b->service.' — '.$b->when().'. The time is free again.')
@section('body')
    @include('emails.partials.booking-details', ['b' => $b])
    <p style="margin:24px 0 0;"><a href="{{ route('booking-page-builder.editor') }}" style="display:inline-block;padding:12px 22px;border-radius:999px;background:#17a85f;color:#fff;font-weight:600;text-decoration:none;">See all bookings</a></p>
@endsection
@section('footer_note', 'You get this because a booking on your booking page '.$b->page->publicUrl().' was cancelled.')
@endif
