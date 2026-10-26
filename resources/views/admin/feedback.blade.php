@extends('admin.layout')
@section('title', 'Feedback')

@section('content')
    <div class="head">
        <div>
            <h1>Feedback</h1>
            <p class="muted">Answers to “What stopped you from getting a quote today?”, asked when a visitor is about to leave without sending an enquiry.</p>
        </div>
    </div>

    <section class="stats">
        <div class="panel stat"><span>Total answers</span><strong>{{ $stats['total'] }}</strong></div>
        <div class="panel stat stat--accent"><span>New since last visit</span><strong>{{ $stats['new'] }}</strong></div>
        <div class="panel stat"><span>Last 7 days</span><strong>{{ $stats['week'] }}</strong></div>
        <div class="panel stat"><span>Left an email</span><strong>{{ $stats['withEmail'] }}</strong></div>
    </section>

    @if ($stats['total'])
        <section class="panel services">
            <h2>Why visitors left</h2>
            @php $max = max(1, $breakdown->max('n')); @endphp
            <ul>
                @foreach ($breakdown as $b)
                    <li>
                        <span class="services__name">{{ $b['label'] }}</span>
                        <span class="services__bar"><i style="width: {{ round($b['n'] / $max * 100) }}%"></i></span>
                        <span class="services__count">{{ $b['n'] }} · {{ round($b['n'] / $stats['total'] * 100) }}%</span>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    <nav class="filters" aria-label="Filter answers">
        <a href="{{ route('admin.feedback.index') }}" @class(['chip', 'chip--on' => ! $reason])>All</a>
        @foreach ($breakdown as $b)
            <a href="{{ route('admin.feedback.index', ['reason' => $b['key']]) }}" @class(['chip', 'chip--on' => $reason === $b['key']])>{{ $b['label'] }} ({{ $b['n'] }})</a>
        @endforeach
    </nav>

    <section class="panel list">
        @forelse ($responses as $r)
            <div class="row feedback-row {{ $r->read_at ? '' : 'is-new' }}">
                <span class="dot" title="{{ $r->read_at ? 'Seen' : 'New' }}"></span>
                <span class="what">
                    <span class="tags">
                        <em class="src--popup">{{ $r->reasonLabel() }}</em>
                        @if ($r->device)<em>{{ ucfirst($r->device) }}</em>@endif
                        @if ($r->page && $r->page !== '/')<em>{{ $r->page }}</em>@endif
                    </span>
                    @if ($r->comment)<span class="feedback-row__comment">“{{ $r->comment }}”</span>@endif
                    @if ($r->email)
                        <span class="feedback-row__email">Follow up: <a href="mailto:{{ $r->email }}?subject={{ rawurlencode('Your visit to Owlvix') }}" class="link">{{ $r->email }}</a></span>
                    @endif
                </span>
                <time datetime="{{ $r->created_at->toIso8601String() }}" title="{{ $r->created_at->format('d M Y, H:i') }}">{{ $r->created_at->diffForHumans(short: true) }}</time>
                <form method="POST" action="{{ route('admin.feedback.destroy', $r) }}" onsubmit="return confirm('Delete this answer?')">
                    @csrf @method('DELETE')
                    <button type="submit" class="btn btn--danger btn--sm" aria-label="Delete answer">Delete</button>
                </form>
            </div>
        @empty
            <div class="empty">
                <img src="{{ asset('images/logo-mark.svg') }}" alt="" width="48" height="48">
                <p>{{ $reason ? 'No answers with this reason yet.' : 'No answers yet. They appear here when visitors answer the survey.' }}</p>
            </div>
        @endforelse
    </section>

    @if ($responses->hasPages())
        <nav class="pager">
            @if ($responses->onFirstPage())
                <span class="btn btn--ghost is-disabled">← Newer</span>
            @else
                <a class="btn btn--ghost" href="{{ $responses->previousPageUrl() }}">← Newer</a>
            @endif
            <span class="muted">Page {{ $responses->currentPage() }} of {{ $responses->lastPage() }}</span>
            @if ($responses->hasMorePages())
                <a class="btn btn--ghost" href="{{ $responses->nextPageUrl() }}">Older →</a>
            @else
                <span class="btn btn--ghost is-disabled">Older →</span>
            @endif
        </nav>
    @endif
@endsection
