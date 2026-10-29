{{-- "Page not found": keeps visitors on the site with links to the main pages. --}}
@extends('layouts.page')

@section('title', 'Page not found')
@section('robots', 'noindex, follow')
@section('no-auto-quote', '1')

@section('content')
<section class="section error-page">
    <div class="container container--narrow">
        <img src="{{ asset('images/owl.png') }}" alt="Owlvix owl mascot" width="140" height="147">
        <p class="eyebrow">Error 404</p>
        <h1>Hoo-hoo… this page flew away</h1>
        <p class="lead">The page you're looking for doesn't exist or has moved. Try one of these instead:</p>
        <div class="error-page__links">
            <a href="{{ route('landing') }}" class="btn">See our services</a>
            <a href="{{ route('home') }}" class="btn btn--ghost">Go to the homepage</a>
        </div>
    </div>
</section>
@endsection
