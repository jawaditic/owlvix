{{-- An order that couldn't be placed in a shop made with the online store builder. --}}
@extends('builder.layout')

@section('title', 'Order not placed')
@section('credit', 'Shop made with the free online store builder by')

@section('content')
    <div class="icon icon--error"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 8v5M12 16.5v.5"/><circle cx="12" cy="12" r="9"/></svg></div>
    <h1>Your order wasn’t placed</h1>
    <ul class="errors">
        @foreach ($errors_list as $error)
            <li>{{ $error }}</li>
        @endforeach
    </ul>
    <p>Nothing was charged.</p>
    @if ($back)
        <a class="btn" href="{{ $back }}#cart">Go back to your cart</a>
    @else
        <p>Please go back and try again.</p>
    @endif
@endsection
