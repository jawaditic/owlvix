@extends('admin.layout')
@section('title', 'Traffic')

@section('content')
    @php
        $hbars = function ($rows, $label) {
            $max = max(1, $rows->max('n') ?? 1);
            return ['rows' => $rows, 'max' => $max, 'label' => $label];
        };
        $sourceIcons = ['Google' => '🔎', 'Direct' => '↪', 'WhatsApp' => '💬', 'Facebook' => '📘', 'Instagram' => '📸', 'LinkedIn' => '💼', 'Fiverr' => '🟢', 'Email' => '✉', 'ChatGPT' => '🤖', 'X (Twitter)' => '𝕏', 'YouTube' => '▶', 'Bing' => '🔎'];
        $deviceIcons = ['mobile' => '📱', 'desktop' => '🖥', 'tablet' => '📟'];
    @endphp

    <div class="head">
        <div>
            <h1>Traffic</h1>
            <p class="muted">Who visits owlvix.com, from where, and how they found you. No cookies; your own visits while logged in aren't counted.</p>
        </div>
        <div class="seg" role="group" aria-label="Period">
            @foreach ([7, 30, 90] as $d)
                <a href="{{ route('admin.traffic', ['days' => $d]) }}" @class(['seg__link', 'is-active' => $days === $d])>{{ $d }} days</a>
            @endforeach
        </div>
    </div>

    <section class="kpis">
        <div class="panel kpi">
            <span class="kpi__label">Visitors · last {{ $days }} days</span>
            <strong class="kpi__value">{{ number_format($stats['visitors']) }}</strong>
            <span class="kpi__note">{{ number_format($stats['views']) }} page views · {{ $stats['perVisitor'] }} per visitor</span>
        </div>
        <div class="panel kpi {{ $stats['live'] ? 'kpi--alert' : '' }}">
            <span class="kpi__label">Today</span>
            <strong class="kpi__value">{{ $stats['today'] }}</strong>
            <span class="kpi__note">@if ($stats['live'])<span class="live-pill"><i></i>{{ $stats['live'] }} on the site now</span>@else visitors so far today @endif</span>
        </div>
        <div class="panel kpi">
            <span class="kpi__label">New visitors</span>
            <strong class="kpi__value">{{ $stats['visitors'] ? $stats['newShare'].'%' : '—' }}</strong>
            <span class="kpi__note">first time on the site; the rest came back</span>
        </div>
        <div class="panel kpi">
            <span class="kpi__label">Visitors who sent an enquiry</span>
            <strong class="kpi__value">{{ $stats['conversion'] === null ? '—' : $stats['conversion'].'%' }}</strong>
            <span class="kpi__note">{{ $stats['enquiries'] }} {{ \Illuminate\Support\Str::plural('enquiry', $stats['enquiries']) }} in the same period</span>
        </div>
    </section>

    <section class="panel chart-card">
        <header class="chart-card__head">
            <div>
                <h2>Visitors over time</h2>
                <p class="muted" id="trend-summary"></p>
            </div>
            <div class="seg" role="group" aria-label="Chart range">
                <button type="button" data-range="7">7 days</button>
                <button type="button" data-range="30" class="is-active">30 days</button>
                <button type="button" data-range="90">90 days</button>
            </div>
        </header>
        <div class="legend">
            <span><i class="sw sw--1"></i>New <b id="leg-new"></b></span>
            <span><i class="sw sw--2"></i>Returning <b id="leg-returning"></b></span>
        </div>
        <div class="chart" id="trend" role="img" aria-label="Visitors per day, new and returning"></div>
        <details class="table-view">
            <summary>View as table</summary>
            <div id="trend-table"></div>
        </details>
    </section>

    @if ($stats['visitors'] === 0)
        <section class="panel chart-card">
            <p class="empty">No visits recorded in this period yet. Stats start from the moment this feature went live — check back soon.</p>
        </section>
    @else
        <div class="grid-2">
            <section class="panel chart-card">
                <h2>Countries</h2>
                <p class="muted">Visitors by country</p>
                @php($max = max(1, $countries->max('n')))
                <ul class="hbars hbars--flags">
                    @foreach ($countries as $c)
                        <li title="{{ $c['name'] }}: {{ $c['n'] }}">
                            <span class="hbars__name"><span class="flag" aria-hidden="true">{{ $c['flag'] }}</span>{{ $c['name'] }}</span>
                            <span class="hbars__track"><i style="width: {{ max(2, round($c['n'] / $max * 100)) }}%"></i></span>
                            <span class="hbars__val">{{ $c['n'] }}</span>
                        </li>
                    @endforeach
                </ul>
            </section>

            <section class="panel chart-card">
                <h2>How they found you</h2>
                <p class="muted">Visitors by source · “Direct” means typed in, bookmarks or apps that hide the link</p>
                @php($max = max(1, $sources->max('n')))
                <ul class="hbars hbars--flags">
                    @foreach ($sources as $s)
                        <li title="{{ $s['key'] }}: {{ $s['n'] }}">
                            <span class="hbars__name"><span class="flag" aria-hidden="true">{{ $sourceIcons[$s['key']] ?? '🔗' }}</span>{{ $s['key'] }}</span>
                            <span class="hbars__track"><i style="width: {{ max(2, round($s['n'] / $max * 100)) }}%"></i></span>
                            <span class="hbars__val">{{ $s['n'] }}</span>
                        </li>
                    @endforeach
                </ul>
            </section>
        </div>

        <div class="grid-2">
            <section class="panel chart-card">
                <h2>Devices</h2>
                @php($dTotal = max(1, $devices->sum('n')))
                <ul class="hbars hbars--flags">
                    @foreach ($devices as $d)
                        <li title="{{ ucfirst($d['key']) }}: {{ $d['n'] }}">
                            <span class="hbars__name"><span class="flag" aria-hidden="true">{{ $deviceIcons[$d['key']] ?? '' }}</span>{{ ucfirst($d['key']) }}</span>
                            <span class="hbars__track"><i style="width: {{ max(2, round($d['n'] / $dTotal * 100)) }}%"></i></span>
                            <span class="hbars__val">{{ round($d['n'] / $dTotal * 100) }}%</span>
                        </li>
                    @endforeach
                </ul>
                <h2 class="chart-card__sub">Browsers</h2>
                @php($bTotal = max(1, $browsers->sum('n')))
                <ul class="hbars">
                    @foreach ($browsers as $b)
                        <li title="{{ $b['key'] }}: {{ $b['n'] }}">
                            <span class="hbars__name">{{ $b['key'] ?: 'Other' }}</span>
                            <span class="hbars__track"><i style="width: {{ max(2, round($b['n'] / $bTotal * 100)) }}%"></i></span>
                            <span class="hbars__val">{{ round($b['n'] / $bTotal * 100) }}%</span>
                        </li>
                    @endforeach
                </ul>
            </section>

            <section class="panel chart-card">
                <h2>Pages</h2>
                <p class="muted">Page views</p>
                @php($max = max(1, $pages->max('n')))
                <ul class="hbars">
                    @foreach ($pages as $p)
                        <li title="{{ $p->path }}: {{ $p->n }}">
                            <span class="hbars__name">{{ $p->path === '/' ? 'Homepage' : (str_starts_with($p->path, '/thanks') ? 'Thank-you page' : $p->path) }}</span>
                            <span class="hbars__track"><i style="width: {{ max(2, round($p->n / $max * 100)) }}%"></i></span>
                            <span class="hbars__val">{{ $p->n }}</span>
                        </li>
                    @endforeach
                </ul>
            </section>
        </div>
    @endif

    <section class="panel chart-card">
        <h2>Latest visits</h2>
        @forelse ($recent as $v)
            <div class="visit-row">
                <span class="visit-row__flag" title="{{ \App\Support\Country::name($v->country) }}">{{ \App\Support\Country::flag($v->country) }}</span>
                <span class="visit-row__main">
                    <strong>{{ \App\Support\Country::name($v->country) }}</strong>
                    <small>{{ $v->source }}{{ $v->referrer_host && $v->referrer_host !== strtolower($v->source) ? ' · '.$v->referrer_host : '' }} · {{ $deviceIcons[$v->device] ?? '' }} {{ ucfirst($v->device) }} · {{ $v->browser }}</small>
                </span>
                <span class="visit-row__meta">
                    @if ($v->is_new)<em class="pill-new">New</em>@endif
                    <time datetime="{{ $v->created_at->toIso8601String() }}" title="{{ $v->created_at->format('d M Y, H:i') }}">{{ $v->created_at->diffForHumans(null, true) }}</time>
                </span>
            </div>
        @empty
            <p class="empty">No visits yet.</p>
        @endforelse
    </section>

    <p class="muted" style="margin-top:4px">For deeper reports (search keywords, sessions, real-time map), see Google Analytics.</p>

    <div class="chart-tip" id="chart-tip" hidden></div>
    @php($chart = ['daily' => $daily, 'series' => [['key' => 'new', 'label' => 'New'], ['key' => 'returning', 'label' => 'Returning']], 'noun' => ['visitor', 'visitors']])
    <script>window.DASHBOARD = {!! json_encode($chart, JSON_HEX_TAG | JSON_UNESCAPED_UNICODE) !!};</script>
    <script src="{{ \App\Support\Asset::url('js/admin-dashboard.js') }}" defer></script>
@endsection
