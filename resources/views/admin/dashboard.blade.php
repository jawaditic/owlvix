@extends('admin.layout')
@section('title', 'Dashboard')

@section('content')
    @php
        $hour = now()->hour;
        $greeting = $hour < 12 ? 'Good morning' : ($hour < 18 ? 'Good afternoon' : 'Good evening');
        $delta = $stats['thisMonth'] - $stats['lastMonth'];
        $fmtHours = function ($h) {
            if ($h === null) return '—';
            if ($h < 1) return max(1, round($h * 60)).' min';
            if ($h < 48) return round($h, 1).' h';
            return round($h / 24, 1).' days';
        };
    @endphp

    <div class="head">
        <div>
            <h1>{{ $greeting }} 👋</h1>
            <p class="muted">{{ now()->format('l, j F Y') }} · Here's how Owlvix is doing.</p>
        </div>
        <div class="head__actions">
            <a href="{{ route('admin.projects.create') }}" class="btn btn--ghost">+ Project</a>
            <a href="{{ route('admin.testimonials.create') }}" class="btn btn--ghost">+ Review</a>
            <a href="{{ route('admin.messages.index') }}" class="btn">Open inbox</a>
        </div>
    </div>

    {{-- Key numbers --}}
    <section class="kpis">
        <div class="panel kpi">
            <span class="kpi__label">Enquiries this month</span>
            <strong class="kpi__value">{{ $stats['thisMonth'] }}</strong>
            <span class="kpi__note">
                @if ($stats['lastMonth'] === 0 && $stats['thisMonth'] === 0)
                    No enquiries yet this month
                @elseif ($delta > 0)
                    <span class="trend trend--up">▲ {{ $delta }}</span> more than this time last month
                @elseif ($delta < 0)
                    <span class="trend trend--down">▼ {{ abs($delta) }}</span> fewer than this time last month
                @else
                    Same as this time last month
                @endif
            </span>
        </div>
        <a href="{{ route('admin.messages.index', ['status' => 'unread']) }}" class="panel kpi kpi--link {{ $stats['unread'] ? 'kpi--alert' : '' }}">
            <span class="kpi__label">Waiting for you</span>
            <strong class="kpi__value">{{ $stats['unread'] }}</strong>
            <span class="kpi__note">{{ $stats['unread'] ? 'unread '.\Illuminate\Support\Str::plural('enquiry', $stats['unread']).' → open' : 'All caught up ✓' }}</span>
        </a>
        <div class="panel kpi">
            <span class="kpi__label">Reply rate</span>
            <strong class="kpi__value">{{ $stats['replyRate'] === null ? '—' : $stats['replyRate'].'%' }}</strong>
            <span class="kpi__note">{{ $stats['replied'] }} of {{ $stats['total'] }} enquiries answered</span>
        </div>
        <div class="panel kpi">
            <span class="kpi__label">Average first reply</span>
            <strong class="kpi__value">{{ $fmtHours($stats['avgReplyHours']) }}</strong>
            <span class="kpi__note">from enquiry to your first email</span>
        </div>
    </section>

    {{-- Enquiries over time --}}
    <section class="panel chart-card">
        <header class="chart-card__head">
            <div>
                <h2>Enquiries over time</h2>
                <p class="muted" id="trend-summary"></p>
            </div>
            <div class="seg" role="tablist" aria-label="Time range">
                <button type="button" data-range="7">7 days</button>
                <button type="button" data-range="30" class="is-active">30 days</button>
                <button type="button" data-range="90">90 days</button>
            </div>
        </header>
        <div class="legend">
            <span><i class="sw sw--1"></i>Quote popup <b id="leg-popup"></b></span>
            <span><i class="sw sw--2"></i>Contact form <b id="leg-contact"></b></span>
        </div>
        <div class="chart" id="trend" role="img" aria-label="Enquiries per day, stacked by form"></div>
        <details class="table-view">
            <summary>View as table</summary>
            <div id="trend-table"></div>
        </details>
    </section>

    <div class="grid-2">
        {{-- What people need --}}
        <section class="panel chart-card">
            <h2>What people ask for</h2>
            <p class="muted">All enquiries, by service</p>
            @if ($services->isEmpty())
                <p class="empty">No enquiries yet.</p>
            @else
                @php($max = max($services->max(), 1))
                <ul class="hbars">
                    @foreach ($services as $name => $n)
                        <li title="{{ $name }}: {{ $n }}">
                            <span class="hbars__name">{{ $name }}</span>
                            <span class="hbars__track"><i style="width: {{ max(2, round($n / $max * 100)) }}%"></i></span>
                            <span class="hbars__val">{{ $n }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>

        {{-- Where they come from + budget --}}
        <section class="panel chart-card">
            <h2>Where enquiries come from</h2>
            @php($srcTotal = max(1, $sources['popup'] + $sources['contact']))
            <div class="split" role="img" aria-label="Quote popup {{ $sources['popup'] }}, contact form {{ $sources['contact'] }}">
                @if ($sources['popup'])<i class="split__seg sw--1" style="flex: {{ $sources['popup'] }}" title="Quote popup: {{ $sources['popup'] }}"></i>@endif
                @if ($sources['contact'])<i class="split__seg sw--2" style="flex: {{ $sources['contact'] }}" title="Contact form: {{ $sources['contact'] }}"></i>@endif
            </div>
            <div class="legend legend--split">
                <span><i class="sw sw--1"></i>Quote popup <b>{{ $sources['popup'] }}</b> <small>{{ round($sources['popup'] / $srcTotal * 100) }}%</small></span>
                <span><i class="sw sw--2"></i>Contact form <b>{{ $sources['contact'] }}</b> <small>{{ round($sources['contact'] / $srcTotal * 100) }}%</small></span>
            </div>

            <h2 class="chart-card__sub">Budget</h2>
            @php($bmax = max(1, $budgets->max()))
            <div class="vbars" role="img" aria-label="Enquiries by budget">
                @foreach ($budgets as $label => $n)
                    <div class="vbars__col" title="{{ $label }}: {{ $n }}">
                        <span class="vbars__val">{{ $n }}</span>
                        <span class="vbars__bar" style="height: {{ $n ? max(4, round($n / $bmax * 100)) : 0 }}%"></span>
                        <span class="vbars__label">{{ $label }}</span>
                    </div>
                @endforeach
            </div>
        </section>
    </div>

    <div class="grid-2 grid-2--wide">
        {{-- Latest enquiries --}}
        <section class="panel chart-card">
            <header class="chart-card__head">
                <h2>Latest enquiries</h2>
                <a href="{{ route('admin.messages.index') }}" class="detail__email">See all →</a>
            </header>
            @forelse ($recent as $m)
                <a href="{{ route('admin.messages.show', $m) }}" class="mini-row {{ $m->isUnread() ? 'is-new' : '' }}">
                    <span class="mini-row__avatar" aria-hidden="true">{{ strtoupper(mb_substr($m->name, 0, 1)) }}</span>
                    <span class="mini-row__main">
                        <strong>{{ $m->name }}</strong>
                        <small>{{ $m->service ?: 'Enquiry' }} · {{ \Illuminate\Support\Str::limit($m->messageText(), 60) }}</small>
                    </span>
                    <span class="mini-row__meta">
                        @if ($m->isUnread())<em class="pill-new">New</em>@elseif ($m->replies_count)<em class="pill-done">✓ Replied</em>@endif
                        <time datetime="{{ $m->created_at->toIso8601String() }}">{{ $m->created_at->diffForHumans(null, true) }}</time>
                    </span>
                </a>
            @empty
                <p class="empty">No enquiries yet. They'll show up here.</p>
            @endforelse
        </section>

        {{-- Site content --}}
        <section class="panel chart-card">
            <h2>Site content</h2>
            <a href="{{ route('admin.projects.index') }}" class="content-tile">
                <span class="content-tile__icon"><svg viewBox="0 0 24 24"><path d="M4 5h7v6H4z M13 5h7v6h-7z M4 13h7v6H4z M13 13h7v6h-7z"/></svg></span>
                <span><strong>{{ $content['projects'] }}</strong> portfolio projects on the site
                    @if ($content['projectsTotal'] > $content['projects'])<small>{{ $content['projectsTotal'] - $content['projects'] }} hidden</small>@endif
                </span>
            </a>
            <a href="{{ route('admin.testimonials.index') }}" class="content-tile">
                <span class="content-tile__icon"><svg viewBox="0 0 24 24"><path d="M12 3.5l2.6 5.3 5.9.9-4.3 4.1 1 5.8-5.2-2.7-5.2 2.7 1-5.8L3.5 9.7l5.9-.9z"/></svg></span>
                <span><strong>{{ $content['reviews'] }}</strong> client reviews on the site
                    @if ($content['reviewsTotal'] > $content['reviews'])<small>{{ $content['reviewsTotal'] - $content['reviews'] }} hidden</small>@endif
                </span>
            </a>
            <div class="content-tile content-tile--plain">
                <span class="content-tile__icon"><svg viewBox="0 0 24 24"><path d="M6 3h4l2 5-3 2a11 11 0 0 0 5 5l2-3 5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 4 5a2 2 0 0 1 2-2"/></svg></span>
                <span><strong>{{ $stats['withPhone'] }}</strong> of {{ $stats['total'] }} enquiries left a phone number</span>
            </div>
        </section>
    </div>

    <div class="chart-tip" id="chart-tip" hidden></div>
    <script>window.DASHBOARD = @json(['daily' => $daily]);</script>
    <script src="{{ asset('js/admin-dashboard.js') }}?v={{ filemtime(public_path('js/admin-dashboard.js')) }}" defer></script>
@endsection
