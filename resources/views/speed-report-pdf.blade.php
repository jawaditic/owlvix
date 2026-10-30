{{-- Speed test report as an A4 PDF (rendered by dompdf in SpeedTestController@pdf).
     dompdf supports tables, floats, borders and solid colours well — no flexbox, grid or gradients. --}}
@php
    $c = ['good' => '#16a34a', 'average' => '#d97706', 'poor' => '#dc2626'];
    $soft = ['good' => '#dcfce7', 'average' => '#fef3c7', 'poor' => '#fee2e2'];
    $label = ['good' => 'Good', 'average' => 'Needs improvement', 'poor' => 'Poor'];
    $mark = ['good' => '✓', 'average' => '!', 'poor' => '✕'];
    $headline = ['good' => 'Your site is fast', 'average' => 'Your site could be faster', 'poor' => 'Your site is slow'][$r['rating']];
    $impact = ['high' => ['High impact', '#dc2626', '#fee2e2'], 'medium' => ['Medium impact', '#d97706', '#fef3c7'], 'low' => ['Low impact', '#16a34a', '#dcfce7']];
    $tested = \Illuminate\Support\Carbon::parse($r['tested_at']);
    $host = parse_url($r['final_url'] ?? $r['url'], PHP_URL_HOST);
    $counts = collect($r['metrics'])->countBy('rating');
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Website speed report — {{ $host }}</title>
<style>
    @page { margin: 0 0 36px 0; }
    * { box-sizing: border-box; }
    body { margin: 0; font-family: 'DejaVu Sans', sans-serif; font-size: 9.5pt; color: #0f172a; line-height: 1.45; }
    .wrap { padding: 0 40px; }
    table { border-collapse: collapse; width: 100%; }
    td { vertical-align: top; }
    .muted { color: #64748b; }
    .small { font-size: 8pt; }

    /* Header band */
    .band { background: #0b1324; color: #fff; padding: 22px 40px 20px; }
    .band .brand { font-size: 16pt; font-weight: bold; letter-spacing: -.3px; }
    .band .brand span { color: #34e08f; }
    .band .doc { text-align: right; font-size: 8.5pt; color: #94a3b8; }
    .band .doc strong { display: block; font-size: 11pt; color: #fff; }
    .accent { height: 4px; background: #12b76a; }
    .accent div { width: 45%; height: 4px; background: #4f8cff; margin-left: 55%; }

    /* Site line */
    .site { padding: 18px 40px 6px; }
    .site .url { font-size: 13pt; font-weight: bold; }
    .pill { display: inline-block; padding: 2px 8px; border-radius: 9px; font-size: 7.5pt; font-weight: bold; }

    /* Score card */
    .card { border: 1px solid #e2e8f0; border-radius: 12px; padding: 16px 18px; background: #fff; }
    .score { width: 112px; height: 112px; border-radius: 65px; border: 9px solid; text-align: center; box-sizing: content-box; }
    .score .n { font-size: 30pt; font-weight: bold; line-height: 1; padding-top: 26px; }
    .score .l { font-size: 7pt; color: #64748b; }
    h1 { font-size: 17pt; margin: 2px 0 6px; }
    h2 { font-size: 12.5pt; margin: 22px 0 10px; }
    h2 small { font-size: 8pt; font-weight: normal; color: #64748b; }
    .eyebrow { font-size: 7.5pt; font-weight: bold; color: #12b76a; letter-spacing: 1.2px; text-transform: uppercase; }
    .shot { width: 118px; border: 1px solid #e2e8f0; border-radius: 8px; }
    .tally td { padding: 0 10px 0 0; font-size: 8pt; }
    .dot { display: inline-block; width: 7px; height: 7px; border-radius: 4px; margin-right: 3px; }

    /* Metric cards */
    .metrics td.cell { width: 33.33%; padding: 0 5px 10px; }
    .metric { border: 1px solid #e2e8f0; border-radius: 10px; padding: 10px 12px 9px; background: #fff; }
    .stat { display: inline-block; width: 15px; height: 13px; padding-top: 2px; border-radius: 4px; color: #fff; font-size: 7.5pt; font-weight: bold; text-align: center; line-height: 1; margin-right: 5px; vertical-align: middle; }
    .metric .top { font-size: 8pt; font-weight: bold; color: #334155; }
    .metric .abbr { float: right; font-size: 6.5pt; color: #64748b; border: 1px solid #e2e8f0; border-radius: 4px; padding: 0 4px; }
    .metric .val { font-size: 17pt; font-weight: bold; margin: 6px 0 2px; }
    .metric .help { font-size: 7pt; color: #64748b; margin-top: 6px; line-height: 1.35; }
    .scale { position: relative; height: 6px; margin-top: 7px; }
    .scale .z { position: absolute; top: 0; height: 6px; }
    .scale .m { position: absolute; top: -3px; width: 8px; height: 8px; border-radius: 6px; background: #fff; border: 2px solid; margin-left: -6px; }
    .scale-l { font-size: 6pt; color: #94a3b8; margin-top: 3px; }
    .scale-l span { display: inline-block; width: 33%; }

    /* Fixes */
    .fix { border: 1px solid #e2e8f0; border-radius: 10px; padding: 10px 12px; margin-bottom: 8px; page-break-inside: avoid; }
    .num { width: 22px; height: 18px; padding-top: 4px; border-radius: 6px; background: #12b76a; color: #fff; font-weight: bold; text-align: center; line-height: 1; font-size: 9pt; }
    .fix .t { font-weight: bold; font-size: 9.5pt; }
    .fix .d { font-size: 8pt; color: #475569; margin-top: 3px; }
    .save { font-weight: bold; color: #15803d; background: #dcfce7; border-radius: 9px; padding: 3px 8px; font-size: 8pt; white-space: nowrap; }
    .bar { height: 4px; background: #eef2f7; border-radius: 3px; margin-top: 7px; }
    .bar div { height: 4px; border-radius: 3px; background: #4f8cff; }

    /* Next steps */
    .next { background: #0b1324; color: #e2e8f0; border-radius: 12px; padding: 18px 20px; margin-top: 16px; page-break-inside: avoid; }
    .next h3 { color: #fff; font-size: 13pt; margin: 0 0 6px; }
    .next .g { color: #34e08f; font-weight: bold; }
    .next ol { margin: 8px 0 0 16px; padding: 0; font-size: 8.5pt; }
    .next li { margin-bottom: 3px; }
    .cta { display: inline-block; background: #12b76a; color: #04130b; font-weight: bold; padding: 7px 14px; border-radius: 8px; text-decoration: none; margin-top: 10px; }
    .legend td { font-size: 7.5pt; padding: 3px 8px 3px 0; color: #475569; }
</style>
</head>
<body>

<div class="band">
    <table><tr>
        <td style="width:60%"><img src="{{ public_path('apple-touch-icon.png') }}" width="30" height="30" style="vertical-align:middle;border-radius:7px"> <span class="brand" style="vertical-align:middle">Owl<span>vix</span></span></td>
        <td class="doc"><strong>Website Speed Report</strong>{{ $tested->format('j F Y, H:i') }} UTC</td>
    </tr></table>
</div>
<div class="accent"><div></div></div>

<div class="site">
    <span class="eyebrow">Tested page</span><br>
    <span class="url">{{ $r['final_url'] ?? $r['url'] }}</span>
    &nbsp;<span class="pill" style="background:#eef2ff;color:#3730a3">{{ $r['strategy'] === 'desktop' ? 'Desktop' : 'Mobile' }}</span>
    <span class="pill" style="background:#f1f5f9;color:#475569">Google Lighthouse</span>
</div>

<div class="wrap">
    {{-- Score + verdict + screenshot --}}
    <div class="card" style="margin-top:10px">
        <table><tr>
            <td style="width:130px">
                <div class="score" style="border-color: {{ $c[$r['rating']] }}">
                    <div class="n" style="color: {{ $c[$r['rating']] }}">{{ $r['score'] }}</div>
                    <div class="l">of 100</div>
                </div>
            </td>
            <td style="padding-right:12px">
                <div class="eyebrow">Performance score</div>
                <h1>{{ $headline }}</h1>
                <div style="color:#334155">{{ $r['summary'] }}</div>
                <table class="tally" style="width:auto;margin-top:10px"><tr>
                    @foreach (['good', 'average', 'poor'] as $k)
                        <td><span class="dot" style="background:{{ $c[$k] }}"></span>{{ $counts[$k] ?? 0 }} {{ strtolower($label[$k]) }}</td>
                    @endforeach
                </tr></table>
            </td>
            @if (! empty($r['screenshot']))
                <td style="width:122px;text-align:right"><img class="shot" src="{{ $r['screenshot'] }}"></td>
            @endif
        </tr></table>
    </div>

    {{-- Key timings --}}
    <h2>Key timings <small>— lab test on a simulated {{ $r['strategy'] === 'desktop' ? 'desktop computer' : 'mid-range phone' }}</small></h2>
    <table class="metrics" style="margin:0 -5px;width:auto">
        @foreach (array_chunk($r['metrics'], 3) as $row)
            <tr>
                @foreach ($row as $m)
                    <td class="cell">
                        <div class="metric">
                            <div class="top"><span class="abbr">{{ $m['abbr'] ?? '' }}</span><span class="stat" style="background: {{ $c[$m['rating']] }}">{{ $mark[$m['rating']] }}</span>{{ $m['label'] }}</div>
                            <div class="val" style="color: {{ $c[$m['rating']] }}">{{ $m['value'] }}</div>
                            <span class="pill" style="background: {{ $soft[$m['rating']] }}; color: {{ $c[$m['rating']] }}">{{ $mark[$m['rating']] }} {{ $label[$m['rating']] }}</span>
                            <div class="scale">
                                <div class="z" style="left:0;width:33%;background:#22c55e"></div>
                                <div class="z" style="left:33%;width:33%;background:#f59e0b"></div>
                                <div class="z" style="left:66%;width:34%;background:#ef4444"></div>
                                <div class="m" style="left: {{ $m['position'] ?? 0 }}%; border-color: {{ $c[$m['rating']] }}"></div>
                            </div>
                            <div class="scale-l"><span>Good</span><span style="text-align:center">Needs work</span><span style="text-align:right">Poor</span></div>
                            <div class="help">{{ $m['help'] }}</div>
                        </div>
                    </td>
                @endforeach
            </tr>
        @endforeach
    </table>

    {{-- Real visitors --}}
    @if (! empty($r['field']))
        <h2 style="margin-top:8px">Real visitors <small>— Chrome users over the last 28 days</small></h2>
        <table class="metrics" style="margin:0 -5px;width:auto"><tr>
            @foreach ($r['field'] as $m)
                <td class="cell">
                    <div class="metric">
                        <div class="top"><span class="abbr">{{ $m['abbr'] ?? '' }}</span><span class="stat" style="background: {{ $c[$m['rating']] }}">{{ $mark[$m['rating']] }}</span>{{ $m['label'] }}</div>
                        <div class="val" style="color: {{ $c[$m['rating']] }}">{{ $m['value'] }}</div>
                        <span class="pill" style="background: {{ $soft[$m['rating']] }}; color: {{ $c[$m['rating']] }}">{{ $mark[$m['rating']] }} {{ $label[$m['rating']] }}</span>
                    </div>
                </td>
            @endforeach
        </tr></table>
    @endif

    {{-- Fixes --}}
    <h2 style="page-break-before: always; margin-top: 30px">What to fix first <small>— ranked by the time each fix could save</small></h2>
    @forelse ($r['fixes'] as $i => $f)
        @php([$il, $ic, $is] = $impact[$f['impact'] ?? 'low'])
        <div class="fix">
            <table><tr>
                <td style="width:32px"><div class="num">{{ $i + 1 }}</div></td>
                <td>
                    <span class="t">{{ $f['title'] }}</span>
                    &nbsp;<span class="pill" style="background: {{ $is }}; color: {{ $ic }}">{{ $il }}</span>
                    <div class="d">{{ $f['description'] }}</div>
                    <div class="bar"><div style="width: {{ $f['share'] ?? 100 }}%"></div></div>
                </td>
                <td style="width:90px;text-align:right"><span class="save">save ~{{ $f['savings'] }}</span></td>
            </tr></table>
        </div>
    @empty
        <div class="fix"><span class="t">No big problems found</span><div class="d">This page already avoids the common speed issues.</div></div>
    @endforelse

    {{-- How to read --}}
    <h2>How to read this report</h2>
    <table class="legend">
        <tr><td style="width:150px"><strong>Performance score</strong></td><td>0–49 poor · 50–89 needs improvement · 90–100 good. Scores can vary a little between runs.</td></tr>
        <tr><td><strong>Core Web Vitals</strong></td><td>LCP (loading), CLS (visual stability) and INP (responsiveness) — the metrics Google uses as a ranking signal.</td></tr>
        <tr><td><strong>Lab vs real visitors</strong></td><td>The lab test is one controlled run; real-visitor data shows what actual Chrome users experienced.</td></tr>
    </table>

    {{-- Next steps --}}
    <div class="next">
        <table><tr>
            <td>
                <h3>Want us to fix it for you?</h3>
                <div>Owlvix fixes speed problems at the source — images, scripts, hosting and code. <span class="g">Start free:</span> we review this report and plan the fixes at no cost, then you pay per milestone, only after you approve each one.</div>
                <ol>
                    <li>Reply to quote@owlvix.com or fill in the form at owlvix.com</li>
                    <li>Get a fixed-price plan within one business day</li>
                    <li>We fix the issues and re-test — you see the new score</li>
                </ol>
                <a class="cta" href="{{ url('/') }}/#contact">Get a free quote → owlvix.com</a>
            </td>
            <td style="width:110px;text-align:right"><img src="{{ public_path('images/owl.png') }}" width="96"></td>
        </tr></table>
    </div>
    <p class="small muted" style="margin-top:10px">Data from Google PageSpeed Insights (Lighthouse). Test again any time at {{ route('speed-test') }}.</p>
</div>
</body>
</html>
