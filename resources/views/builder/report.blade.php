@extends('builder.layout')

@section('title', 'Report this page')
@section('credit', 'Free page builder by')

@section('content')
    @if (session('reported'))
        <div class="icon"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg></div>
        <h1>Thanks for telling us</h1>
        <p>We’ll review this page and take it down if it breaks our rules.</p>
    @else
        <h1>Report this page</h1>
        <p>Is <strong>{{ $site->displayUrl() }}</strong> ({{ $site->name }}) a scam, phishing, spam or otherwise harmful? Tell us and we’ll review it.</p>
        <form method="POST" action="{{ route('builder.report.send', $site->slug) }}">
            @csrf
            <label for="reason">What’s wrong with it?</label>
            <textarea id="reason" name="reason" required minlength="5" maxlength="2000">{{ old('reason') }}</textarea>
            @error('reason')<p class="error">{{ $message }}</p>@enderror
            <label for="email">Your email (optional, if you want a reply)</label>
            <input id="email" type="email" name="email" maxlength="150" value="{{ old('email') }}">
            @error('email')<p class="error">{{ $message }}</p>@enderror
            <input class="hp" type="text" name="website" tabindex="-1" autocomplete="off" aria-hidden="true">
            <button type="submit" class="btn">Send report</button>
        </form>
    @endif
@endsection
