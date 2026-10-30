@extends('admin.layout')
@section('title', $message->name)

@section('content')
    <a href="{{ route('admin.messages.index') }}" class="back">← All enquiries</a>

    <article class="panel detail">
        <header class="detail__head">
            <div>
                <h1>{{ $message->name }}</h1>
                <a href="mailto:{{ $message->email }}" class="detail__email">{{ $message->email }}</a>
            </div>
            <a href="#reply" class="btn">Write a reply</a>
        </header>

        <dl class="facts">
            <div><dt>Received</dt><dd>{{ $message->created_at->format('d M Y, H:i') }}</dd></div>
            <div><dt>Phone</dt><dd>@if ($message->phone)<a href="tel:{{ preg_replace('/[^\d+]/', '', $message->phone) }}">{{ $message->phone }}</a>@else — @endif</dd></div>
            <div><dt>Needs</dt><dd>{{ $message->service ?: '—' }}</dd></div>
            <div><dt>Budget</dt><dd>{{ $message->budget ?: '—' }}</dd></div>
            <div><dt>Sent from</dt><dd>{{ $message->sourceLabel() }}</dd></div>
        </dl>

        <div class="detail__body rich">{!! $message->messageHtml() !!}</div>

        @foreach ($message->replies as $r)
            <div class="reply">
                <p class="reply__meta">
                    <strong>You replied</strong> · {{ $r->created_at->format('d M Y, H:i') }}
                    <span>{{ $r->subject }}</span>
                </p>
                <div class="reply__body">{{ $r->body }}</div>
            </div>
        @endforeach

        <form method="POST" action="{{ route('admin.messages.reply', $message) }}" class="reply-form" id="reply">
            @csrf
            <h2>Reply to {{ $message->firstName() }}</h2>
            <p class="muted">Sent by email from {{ config('mail.from.address') }} and saved here. Their replies arrive in that inbox.</p>
            @if ($errors->any())
                <div class="alert" role="alert">{{ $errors->first() }}</div>
            @endif
            <label>Subject
                <input type="text" name="subject" required maxlength="200"
                    value="{{ old('subject', 'Re: your '.($message->service ? strtolower($message->service) : 'project').' enquiry — Owlvix') }}">
            </label>
            <label>Message
                <textarea name="body" rows="9" required>{{ old('body', "Hi {$message->firstName()},\n\nThanks for reaching out to Owlvix!\n\n\n\nBest regards,\nOwlvix") }}</textarea>
            </label>
            <button type="submit" class="btn">Send reply</button>
        </form>

        <footer class="detail__actions">
            <form method="POST" action="{{ route('admin.messages.read', $message) }}">
                @csrf @method('PATCH')
                <button type="submit" class="btn btn--ghost">Mark as {{ $message->isUnread() ? 'read' : 'unread' }}</button>
            </form>
            <form method="POST" action="{{ route('admin.messages.destroy', $message) }}" data-confirm="Delete this message?" data-confirm-text="This can't be undone." data-confirm-ok="Delete" data-confirm-danger>
                @csrf @method('DELETE')
                <button type="submit" class="btn btn--danger">Delete</button>
            </form>
        </footer>
    </article>
@endsection
