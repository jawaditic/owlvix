@extends('builder.layout')
@php($failed = ! empty($errors_list))
@php($leads = $form->isLeads())
@php($survey = $form->isSurvey())

@section('title', $survey ? ($failed ? 'Not sent' : 'Thank you') : ($leads ? ($failed ? 'Not signed up' : 'You’re signed up') : ($failed ? 'Message not sent' : 'Message sent')))

@section('content')
    @if ($failed)
        <div class="icon icon--error"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 8v5M12 16.5v.5"/><circle cx="12" cy="12" r="9"/></svg></div>
        <h1>{{ $leads ? 'Your details weren’t sent' : 'Your message wasn’t sent' }}</h1>
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
        @if ($survey)
            <h1>Thank you!</h1>
            <p>{{ $form->schema['thanks'] ?? 'Your answers have been sent.' }}</p>
        @elseif ($leads)
            <h1>You’re signed up</h1>
            <p>Thanks! {{ $form->site_name }} has your details.</p>
        @else
            <h1>Message sent</h1>
            <p>{{ $form->site_name }} will get back to you soon.</p>
        @endif
        @if ($back)
            <a class="btn" href="{{ $back }}">{{ $survey ? 'Back to the page' : 'Back to '.$form->site_name }}</a>
        @endif
    @endif
@endsection
