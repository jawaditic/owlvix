{{-- One step-by-step guide (App\Support\Guides). The guide's view passes $g:
     route, title, description, h1, crumb, meta, intro (HTML), cta [top, bottom] as [bold, text, button],
     before[], steps [[title, text, tip?]], mistakes [[bold, text]], more (HTML, optional), faq [[q, a]], faqTitle, alt.
     Marked up as an Article with HowTo steps and an FAQ for search engines. --}}
@php
    $url = route($g['route']);
    $builder = route(\App\Support\Guides::ALL[$g['route']][3]);
    $site = url('/').'#business';
    $ld = ['@context' => 'https://schema.org', '@graph' => [
        ['@type' => 'Article', 'headline' => $g['h1'], 'description' => $g['description'], 'url' => $url, 'datePublished' => $g['date'], 'dateModified' => $g['date'],
            'author' => ['@id' => $site], 'publisher' => ['@id' => $site], 'mainEntityOfPage' => $url],
        ['@type' => 'HowTo', 'name' => $g['h1'], 'totalTime' => $g['time'], 'estimatedCost' => ['@type' => 'MonetaryAmount', 'currency' => 'USD', 'value' => '0'],
            'step' => array_map(fn ($s, $i) => ['@type' => 'HowToStep', 'position' => $i + 1, 'name' => $s[0], 'text' => $s[1], 'url' => $url.'#step-'.($i + 1)], $g['steps'], array_keys($g['steps']))],
        ['@type' => 'FAQPage', 'mainEntity' => array_map(fn ($q) => ['@type' => 'Question', 'name' => $q[0], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $q[1]]], $g['faq'])],
    ]];
    $others = array_filter(\App\Support\Guides::ALL, fn ($k) => $k !== $g['route'], ARRAY_FILTER_USE_KEY);
@endphp

@section('title', $g['title'])
@section('description', $g['description'])
@section('canonical', $url)

@section('head')
<script type="application/ld+json">{!! json_encode($ld, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!}</script>
@endsection

@section('content')
<section class="section">
    <div class="container container--narrow prose guide">
        <nav class="crumbs" aria-label="Breadcrumb">
            <a href="{{ route('home') }}">Home</a><span aria-hidden="true">›</span><a href="{{ $builder }}">{{ $g['crumb'] }}</a><span aria-hidden="true">›</span><span aria-current="page">Guide</span>
        </nav>
        <div class="page-owl">@include('partials.live-owl', ['width' => 120, 'variant' => 'dev', 'alt' => $g['alt']])</div>
        <p class="eyebrow">Guide</p>
        <h1>{{ $g['h1'] }}</h1>
        <p class="prose__updated">{{ count($g['steps']) }} steps · {{ $g['meta'] }}</p>

        {!! $g['intro'] !!}

        <div class="guide__cta card">
            <div><b>{{ $g['cta'][0][0] }}</b><span>{{ $g['cta'][0][1] }}</span></div>
            <a class="btn btn--glow" href="{{ $builder }}">{{ $g['cta'][0][2] }}</a>
        </div>

        <h2>Before you start</h2>
        <ul>
            @foreach ($g['before'] as $item)<li>{!! $item !!}</li>@endforeach
        </ul>

        <ol class="guide__steps">
            @foreach ($g['steps'] as $i => $s)
                <li id="step-{{ $i + 1 }}">
                    <h2><span class="guide__n">{{ $i + 1 }}</span>{{ $s[0] }}</h2>
                    <p>{{ $s[1] }}</p>
                    @if (! empty($s[2]))<p><strong>Tip:</strong> {{ $s[2] }}</p>@endif
                </li>
            @endforeach
        </ol>

        <h2>Common mistakes to avoid</h2>
        <ul>
            @foreach ($g['mistakes'] as [$b, $t])<li><strong>{{ $b }}</strong> {{ $t }}</li>@endforeach
        </ul>

        <div class="guide__cta card">
            <div><b>{{ $g['cta'][1][0] }}</b><span>{{ $g['cta'][1][1] }}</span></div>
            <a class="btn btn--glow" href="{{ $builder }}">{{ $g['cta'][1][2] }}</a>
        </div>
        @if (! empty($g['more'])){!! $g['more'] !!}@endif

        <h2>More free guides</h2>
        <ul class="guide__more">
            @foreach ($others as $route => $o)<li><a href="{{ route($route) }}">{{ $o[2] }}</a></li>@endforeach
        </ul>
    </div>
</section>

@include('tools.partials.faq', ['faq' => $g['faq'], 'title' => $g['faqTitle']])
@endsection
