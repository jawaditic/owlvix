@extends('builder.layout')
@php($failed = ! empty($errors_list))

@section('title', $failed ? 'Message not sent' : 'Message sent')

@section('content')
    @if ($failed)
        <div class="icon icon--error"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 8v5M12 16.5v.5"/><circle cx="12" cy="12" r="9"/></svg></div>
        <h1>Your message wasn’t sent</h1>
        <ul class="errors">
            @foreach ($errors_list as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
        @if ($back)
            <a class="btn" href="{{ $back }}">Go back and try again</a>
        @else
            <p>Please go back and try again.</p>
        @endif
    @else
        <div class="icon"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg></div>
        <h1>Message sent</h1>
        <p>{{ $form->site_name }} will get back to you soon.</p>
        @if ($back)
            <a class="btn" href="{{ $back }}">Back to {{ $form->site_name }}</a>
        @endif
    @endif
@endsection
