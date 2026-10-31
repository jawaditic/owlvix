@extends('builder.layout')

@section('title', $ok ? 'Payment received' : 'Payment not made')
@section('credit', 'Free mobile app builder by')

@section('content')
    @if ($ok)
        <div class="icon"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg></div>
        <h1>Payment received</h1>
        <p>Thank you! Go back to the <strong>{{ $app->name }}</strong> app to send your order.</p>
    @else
        <h1>Payment not made</h1>
        <p>Nothing was charged. Go back to the <strong>{{ $app->name }}</strong> app to try again or choose another way to pay.</p>
    @endif
@endsection
