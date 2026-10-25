@extends('admin.layout')
@section('title', 'Messages')

@section('content')
    <div class="head">
        <div>
            <h1>Enquiries</h1>
            <p class="muted">Everything sent from the quote popup and the contact form.</p>
        </div>
        <a href="{{ route('admin.messages.export', request()->query()) }}" class="btn btn--ghost">Export CSV</a>
    </div>

    <section class="stats">
        <div class="panel stat"><span>Total</span><strong>{{ $stats['total'] }}</strong></div>
        <div class="panel stat stat--accent"><span>New (unread)</span><strong>{{ $stats['unread'] }}</strong></div>
        <div class="panel stat"><span>Last 7 days</span><strong>{{ $stats['week'] }}</strong></div>
        <div class="panel stat"><span>From popup</span><strong>{{ $stats['popup'] }}</strong></div>
    </section>

    @if ($services->isNotEmpty())
        <section class="panel services">
            <h2>What people ask for</h2>
            @php $max = max($services->max(), 1); @endphp
            <ul>
                @foreach ($services as $name => $count)
                    <li>
                        <span class="services__name">{{ $name }}</span>
                        <span class="services__bar"><i style="width: {{ round($count / $max * 100) }}%"></i></span>
                        <span class="services__count">{{ $count }}</span>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    <form method="GET" class="filters">
        <input type="search" name="q" value="{{ request('q') }}" placeholder="Search name, email, phone or message">
        <select name="status">
            <option value="">All</option>
            <option value="unread" @selected(request('status') === 'unread')>New only</option>
        </select>
        <select name="source">
            <option value="">Both forms</option>
            <option value="popup" @selected(request('source') === 'popup')>Quote popup</option>
            <option value="contact" @selected(request('source') === 'contact')>Contact form</option>
        </select>
        <button type="submit" class="btn">Filter</button>
        @if (request()->hasAny(['q', 'status', 'source']))
            <a href="{{ route('admin.messages.index') }}" class="link">Clear</a>
        @endif
    </form>

    <section class="panel list">
        @forelse ($messages as $m)
            <a href="{{ route('admin.messages.show', $m) }}" class="row {{ $m->isUnread() ? 'is-new' : '' }}">
                <span class="dot" title="{{ $m->isUnread() ? 'New' : 'Read' }}"></span>
                <span class="who">
                    <strong>{{ $m->name }}</strong>
                    <small>{{ $m->email }}</small>
                </span>
                <span class="what">
                    <span class="tags">
                        @if ($m->service)<em>{{ $m->service }}</em>@endif
                        @if ($m->budget && $m->budget !== 'Not sure yet')<em>{{ $m->budget }}</em>@endif
                        <em class="src src--{{ $m->source }}">{{ $m->source === 'popup' ? 'Popup' : 'Contact form' }}</em>
                        @if ($m->replies_count)<em class="replied">✓ Replied</em>@endif
                    </span>
                    <span class="preview">{{ \Illuminate\Support\Str::limit($m->messageText(), 140) }}</span>
                </span>
                <time datetime="{{ $m->created_at->toIso8601String() }}" title="{{ $m->created_at->format('d M Y, H:i') }}">{{ $m->created_at->diffForHumans(short: true) }}</time>
            </a>
        @empty
            <div class="empty">
                <img src="{{ asset('images/logo-mark.svg') }}" alt="" width="48" height="48">
                <p>No messages {{ request()->hasAny(['q', 'status', 'source']) ? 'match these filters' : 'yet' }}.</p>
            </div>
        @endforelse
    </section>

    @if ($messages->hasPages())
        <nav class="pager">
            @if ($messages->onFirstPage())
                <span class="btn btn--ghost is-disabled">← Newer</span>
            @else
                <a class="btn btn--ghost" href="{{ $messages->previousPageUrl() }}">← Newer</a>
            @endif
            <span class="muted">Page {{ $messages->currentPage() }} of {{ $messages->lastPage() }}</span>
            @if ($messages->hasMorePages())
                <a class="btn btn--ghost" href="{{ $messages->nextPageUrl() }}">Older →</a>
            @else
                <span class="btn btn--ghost is-disabled">Older →</span>
            @endif
        </nav>
    @endif
@endsection
