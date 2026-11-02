@extends('admin.layout')
@section('title', 'Growth')

@section('content')
    @php
        $pct = fn ($a, $b) => $b ? round($a / $b * 100).'%' : '—';
        $cols = ['open' => 'Opened builder', 'design' => 'Used a design', 'save' => 'Saved', 'publish' => 'Published', 'export' => 'Downloaded'];
    @endphp

    <div class="head">
        <div>
            <h1>Growth</h1>
            <p class="muted">How people reach the free builders — from a guide, the example designs, search or elsewhere — and how far they get. Counted once per visitor session; your own visits while logged in aren't counted.</p>
        </div>
        <div class="seg" role="group" aria-label="Period">
            @foreach ([7, 30, 90] as $d)
                <a href="{{ route('admin.growth', ['days' => $d]) }}" @class(['seg__link', 'is-active' => $days === $d])>{{ $d }} days</a>
            @endforeach
        </div>
    </div>

    <section class="kpis">
        <div class="panel kpi">
            <span class="kpi__label">Builder opens · last {{ $days }} days</span>
            <strong class="kpi__value">{{ number_format($totals['open']) }}</strong>
            <span class="kpi__note">{{ number_format($fromContent) }} came from a guide or the example designs</span>
        </div>
        <div class="panel kpi">
            <span class="kpi__label">Saved to an account</span>
            <strong class="kpi__value">{{ number_format($totals['save']) }}</strong>
            <span class="kpi__note">{{ $pct($totals['save'], $totals['open']) }} of opens</span>
        </div>
        <div class="panel kpi">
            <span class="kpi__label">Published</span>
            <strong class="kpi__value">{{ number_format($totals['publish']) }}</strong>
            <span class="kpi__note">{{ $pct($totals['publish'], $totals['open']) }} of opens</span>
        </div>
        <div class="panel kpi">
            <span class="kpi__label">Downloaded or copied</span>
            <strong class="kpi__value">{{ number_format($totals['export']) }}</strong>
            <span class="kpi__note">HTML, PDF, email code or app files</span>
        </div>
    </section>

    <section class="panel chart-card">
        <header class="chart-card__head"><div><h2>Where builders come from</h2><p class="muted">Guides first, then the example designs. “Visitors” is people who read that page; “Opened” is how many went on to a builder.</p></div></header>
        <div class="growth-table">
            <table>
                <thead><tr><th scope="col">Source</th><th scope="col">Visitors</th>@foreach ($cols as $c)<th scope="col">{{ $c }}</th>@endforeach<th scope="col">Visitor {!! \App\Support\Icons::i('arrow-right') !!} builder</th></tr></thead>
                <tbody>
                    @foreach ($bySource as $s)
                        <tr>
                            <th scope="row">@if ($s['url'])<a class="link" href="{{ $s['url'] }}" target="_blank" rel="noopener">{{ $s['label'] }}</a>@else{{ $s['label'] }}@endif</th>
                            <td>{{ $s['visitors'] === null ? '—' : number_format($s['visitors']) }}</td>
                            @foreach (array_keys($cols) as $e)<td>{{ $s[$e] ? number_format($s[$e]) : '·' }}</td>@endforeach
                            <td>{{ $s['visitors'] ? $pct($s['open'], $s['visitors']) : '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>

    <section class="panel chart-card">
        <header class="chart-card__head"><div><h2>Each builder</h2><p class="muted">Most-opened first. Shows which builders people finish, not just start.</p></div></header>
        @if (! $byBuilder)
            <p class="empty">Nothing yet. Numbers start from the moment this went live — check back soon.</p>
        @else
            <div class="growth-table">
                <table>
                    <thead><tr><th scope="col">Builder</th>@foreach ($cols as $c)<th scope="col">{{ $c }}</th>@endforeach<th scope="col">Published or downloaded</th></tr></thead>
                    <tbody>
                        @foreach ($byBuilder as $b)
                            <tr>
                                <th scope="row">@if ($b['url'])<a class="link" href="{{ $b['url'] }}" target="_blank" rel="noopener">{{ $b['label'] }}</a>@else{{ $b['label'] }}@endif</th>
                                @foreach (array_keys($cols) as $e)<td>{{ $b[$e] ? number_format($b[$e]) : '·' }}</td>@endforeach
                                <td>{{ $pct($b['publish'] + $b['export'], $b['open']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
@endsection
