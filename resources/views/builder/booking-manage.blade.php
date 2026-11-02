{{-- A client's own booking (from the link in their confirmation email): details, add to calendar, cancel. --}}
@extends('builder.layout')
@php($s = $page->settings)

@section('title', $b->service.' — '.$page->name())

@section('content')
    <div class="card">
        @if ($b->isConfirmed())
            <div class="icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg></div>
            <h1>{{ $b->starts_at->isFuture() ? 'You’re booked' : 'Your booking' }}</h1>
        @else
            <div class="icon icon--error" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M6 6l12 12M18 6L6 18"/></svg></div>
            <h1>{{ $done === 'cancelled' ? 'Your booking is cancelled' : 'This booking was cancelled' }}</h1>
        @endif
        <p><b style="color:var(--text)">{{ $b->service }}</b> with {{ $page->name() }}<br>{{ $b->when() }} <span style="white-space:nowrap">({{ $page->tz() }})</span>
            @if (! empty($s['location']))<br>{{ $s['location'] }}@endif</p>
        @if ($b->isConfirmed() && ! empty($s['note']))<p>{{ $s['note'] }}</p>@endif

        @if ($b->cancellable() && $ask)
            <p><b style="color:var(--text)">Cancel this booking?</b> The time goes back on {{ $page->name() }}’s booking page.</p>
            <form method="post" action="{{ url('/book/manage/'.$b->token.'/cancel') }}" style="text-align:center">
                <button type="submit" class="btn" style="background:var(--error)">Yes, cancel it</button>
                <a class="btn" href="{{ url('/book/manage/'.$b->token) }}" style="background:transparent;color:var(--text);border:1px solid var(--line)">Keep it</a>
            </form>
        @elseif ($b->cancellable())
            <a class="btn" href="{{ url('/book/manage/'.$b->token.'/calendar.ics') }}">Add to my calendar</a>
            <p style="margin-top:8px"><a href="{{ url('/book/manage/'.$b->token.'?ask=cancel') }}" style="color:var(--error)">Cancel my booking</a></p>
        @elseif (! $b->isConfirmed())
            <a class="btn" href="{{ $page->publicUrl() }}">Book another time</a>
        @endif
        <p class="small">Need to change something? Contact {{ $page->name() }} — reply to your confirmation email.</p>
    </div>
@endsection
