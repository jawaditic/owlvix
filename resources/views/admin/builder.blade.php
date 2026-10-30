@extends('admin.layout')
@section('title', 'Page builder')

@section('content')
    <div class="head">
        <div>
            <h1>Page builder</h1>
            <p class="muted">Sites published with the free <a class="link" href="{{ route('landing-builder') }}" target="_blank" rel="noopener">page builder</a> (at {{ preg_replace('#^https?://#', '', \App\Models\BuilderSite::base()) }}…), and their contact forms. Take down anything harmful.</p>
        </div>
    </div>

    <section class="stats">
        <div class="panel stat"><span>Builder users</span><strong>{{ $stats['accounts'] }}</strong></div>
        <div class="panel stat"><span>Live sites</span><strong>{{ $stats['live'] }}</strong></div>
        <div @class(['panel stat', 'stat--accent' => $stats['reported']])><span>Reported, live</span><strong>{{ $stats['reported'] }}</strong></div>
        <div class="panel stat"><span>Form messages sent</span><strong>{{ $stats['messages'] }}</strong></div>
        <div class="panel stat"><span>Funnel leads</span><strong>{{ $stats['leads'] }}</strong></div>
    </section>

    <h2>Published sites</h2>
    <section class="panel list">
        @forelse ($sites as $site)
            <div @class(['row feedback-row', 'is-new' => $site->reported_at && $site->isLive()])>
                <span class="dot" title="{{ $site->reported_at ? 'Reported' : '' }}"></span>
                <span class="what">
                    <span class="tags">
                        @if ($site->isLive())
                            <em class="src--contact">Live</em>
                        @else
                            <em>Removed by {{ $site->removed_by ?: 'owner' }}</em>
                        @endif
                        @if ($site->reported_at)<em class="src--popup">Reported {{ $site->reports }}× · {{ $site->reported_at->diffForHumans(short: true) }}</em>@endif
                        <em>{{ count($site->pages ?? []) }} {{ \Illuminate\Support\Str::plural('page', count($site->pages ?? [])) }}</em>
                        <em>{{ number_format($site->views) }} views</em>
                    </span>
                    <span class="feedback-row__comment">
                        @if ($site->isLive())
                            <a class="link" href="{{ $site->url() }}" target="_blank" rel="noopener">{{ $site->displayUrl() }}</a>
                        @else
                            {{ $site->displayUrl() }}
                        @endif
                        — {{ $site->name }}
                    </span>
                    <span class="feedback-row__email">By <a href="mailto:{{ $site->account->email }}" class="link">{{ $site->account->email }}</a></span>
                </span>
                <time datetime="{{ $site->updated_at->toIso8601String() }}" title="Updated {{ $site->updated_at->format('d M Y, H:i') }}">{{ $site->updated_at->diffForHumans(short: true) }}</time>
                <span class="t-row__actions">
                    @if ($site->isLive())
                        @if ($site->reported_at)
                            <form method="POST" action="{{ route('admin.builder.dismiss', $site) }}">
                                @csrf
                                <button type="submit" class="btn btn--ghost btn--sm">Dismiss report</button>
                            </form>
                        @endif
                        <form method="POST" action="{{ route('admin.builder.takedown', $site) }}" onsubmit="return confirm('Take down {{ $site->displayUrl() }}?')">
                            @csrf
                            <button type="submit" class="btn btn--danger btn--sm">Take down</button>
                        </form>
                    @elseif ($site->removed_by === 'admin')
                        <form method="POST" action="{{ route('admin.builder.restore', $site) }}">
                            @csrf
                            <button type="submit" class="btn btn--ghost btn--sm">Restore</button>
                        </form>
                    @endif
                </span>
            </div>
        @empty
            <div class="empty">
                <img src="{{ asset('images/logo-mark.svg') }}" alt="" width="48" height="48">
                <p>No published sites yet.</p>
            </div>
        @endforelse
    </section>

    <h2 style="margin-top: 28px;">Forms</h2>
    <section class="panel list">
        @forelse ($forms as $form)
            <div class="row feedback-row">
                <span class="dot"></span>
                <span class="what">
                    <span class="tags">
                        @if ($form->isLeads())
                            <em class="src--contact">Lead form</em>
                            <em>{{ number_format($form->leads_count) }} {{ \Illuminate\Support\Str::plural('lead', $form->leads_count) }} stored</em>
                        @else
                            <em>Contact form</em>
                            <em>{{ $form->submissions }} {{ \Illuminate\Support\Str::plural('message', $form->submissions) }}</em>
                        @endif
                        @if ($form->disabled_at)<em class="src--popup">Disabled</em>@endif
                        <em>/f/{{ $form->id }}</em>
                    </span>
                    <span class="feedback-row__comment">{{ $form->site_name }}</span>
                    <span class="feedback-row__email">Owner <a href="mailto:{{ $form->account->email }}" class="link">{{ $form->account->email }}</a></span>
                </span>
                <time title="Last message">{{ $form->last_submission_at ? $form->last_submission_at->diffForHumans(short: true) : '—' }}</time>
                <form method="POST" action="{{ route('admin.builder.forms.toggle', $form) }}">
                    @csrf
                    <button type="submit" class="btn btn--ghost btn--sm">{{ $form->disabled_at ? 'Enable' : 'Disable' }}</button>
                </form>
            </div>
        @empty
            <div class="empty">
                <img src="{{ asset('images/logo-mark.svg') }}" alt="" width="48" height="48">
                <p>No forms yet.</p>
            </div>
        @endforelse
    </section>
@endsection
