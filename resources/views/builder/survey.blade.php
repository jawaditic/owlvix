{{-- A form made with the free form builder (/form-builder), shown at /form/{id}. No scripts (the CSP allows none);
     ?embed=1 drops the background and footer for iframes. Answers post to /f/{id}. --}}
@php
    $c = $s['color'] ?? '#16a34a';
    $val = fn ($id) => is_string($old[$id] ?? null) ? $old[$id] : '';
    $checked = fn ($id, $opt) => in_array($opt, is_array($old[$id] ?? null) ? $old[$id] : [], true) || (($old[$id] ?? null) === $opt);
    $report = config('admin.email') ? 'mailto:'.config('admin.email').'?subject='.rawurlencode('Report form '.$form->id) : url('/');
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $s['title'] }}</title>
    <style>
        :root { --c: {{ $c }}; --bg: #f4f6fb; --card: #fff; --text: #0f172a; --muted: #64748b; --line: #e2e8f0; --field: #f8fafc; --error: #b91c1c; }
        @media (prefers-color-scheme: dark) { :root { --bg: #070b14; --card: #0f1626; --text: #e2e8f0; --muted: #94a3b8; --line: #1e293b; --field: #0b1220; --error: #f87171; } }
        * { box-sizing: border-box; }
        body { margin: 0; padding: {{ $embed ? '0' : '32px 16px' }}; background: {{ $embed ? 'transparent' : 'var(--bg)' }}; color: var(--text); font: 16px/1.55 -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; }
        .card { max-width: 640px; margin: 0 auto; background: var(--card); border: 1px solid var(--line); border-top: 8px solid var(--c); border-radius: 18px; padding: 30px 28px; box-shadow: 0 12px 40px rgba(15, 23, 42, .07); }
        h1 { margin: 0 0 8px; font-size: 1.6rem; line-height: 1.25; }
        h2 { margin: 26px 0 4px; font-size: 1.15rem; padding-top: 18px; border-top: 1px solid var(--line); }
        .intro, .help { color: var(--muted); margin: 0 0 6px; }
        .help { font-size: .88rem; margin: -2px 0 8px; }
        .q { margin: 22px 0 0; }
        .q > label, legend { display: block; font-weight: 600; margin-bottom: 6px; padding: 0; }
        fieldset { border: 0; margin: 22px 0 0; padding: 0; min-width: 0; }
        .req { color: var(--error); margin-left: 3px; }
        input[type=text], input[type=email], input[type=tel], input[type=number], input[type=date], select, textarea { width: 100%; padding: 11px 13px; border: 1px solid var(--line); border-radius: 10px; background: var(--field); color: var(--text); font: inherit; }
        input:focus, select:focus, textarea:focus { outline: none; border-color: var(--c); box-shadow: 0 0 0 3px color-mix(in srgb, var(--c) 22%, transparent); }
        textarea { min-height: 110px; resize: vertical; }
        .opt { display: flex; align-items: center; gap: 10px; padding: 7px 0; cursor: pointer; }
        .opt input { width: 18px; height: 18px; accent-color: var(--c); margin: 0; }
        .rating { display: flex; gap: 8px; flex-wrap: wrap; }
        .rating label { position: relative; }
        .rating input { position: absolute; opacity: 0; }
        .rating span { display: grid; place-items: center; width: 46px; height: 46px; border: 1px solid var(--line); border-radius: 12px; background: var(--field); font-weight: 700; cursor: pointer; }
        .rating input:checked + span { background: var(--c); border-color: var(--c); color: #fff; }
        .rating input:focus-visible + span { outline: 2px solid var(--c); outline-offset: 2px; }
        .btn { margin-top: 26px; padding: 13px 26px; border: 0; border-radius: 999px; background: var(--c); color: #fff; font-family: inherit; font-size: 1rem; font-weight: 700; cursor: pointer; }
        .errors { margin: 16px 0 0; padding: 12px 16px 12px 34px; border-radius: 12px; background: color-mix(in srgb, var(--error) 10%, transparent); color: var(--error); }
        .done { text-align: center; padding: 20px 0; }
        .done .tick { width: 64px; height: 64px; margin: 0 auto 14px; border-radius: 50%; display: grid; place-items: center; background: color-mix(in srgb, var(--c) 15%, transparent); color: var(--c); font-size: 30px; }
        .hp { position: absolute; left: -9999px; }
        .note { margin: 22px 0 0; font-size: .78rem; color: var(--muted); }
        .credit { max-width: 640px; margin: 14px auto 0; text-align: center; font-size: .8rem; color: var(--muted); }
        .credit a { color: inherit; }
    </style>
</head>
<body>
<main class="card">
    @if ($sent)
        <div class="done">
            <div class="tick" aria-hidden="true">✓</div>
            <h1>{{ $s['title'] }}</h1>
            <p class="intro">{!! nl2br(e($s['thanks'])) !!}</p>
            <a href="{{ route('builder.survey', ['id' => $form->id] + ($embed ? ['embed' => 1] : [])) }}" style="color: var(--c)">Send another answer</a>
        </div>
    @else
        <h1>{{ $s['title'] }}</h1>
        @if ($s['intro'])<p class="intro">{!! nl2br(e($s['intro'])) !!}</p>@endif
        @if ($errors_list)
            <ul class="errors" role="alert">@foreach ($errors_list as $e)<li>{{ $e }}</li>@endforeach</ul>
        @endif
        <form method="post" action="{{ $form->action() }}">
            <input class="hp" name="_hp" tabindex="-1" autocomplete="off" aria-hidden="true">
            @if ($embed)<input type="hidden" name="_embed" value="1">@endif
            @if (! empty($back))<input type="hidden" name="_back" value="{{ $back }}">@endif
            @foreach ($s['fields'] as $f)
                @php($fid = 'q-'.$f['id'])
                @php($req = $f['required'] ? 'required' : '')
                @php($star = $f['required'] ? '<span class="req" aria-hidden="true">*</span>' : '')
                @if ($f['type'] === 'heading')
                    <h2>{{ $f['label'] }}</h2>
                    @if ($f['help'])<p class="help">{!! nl2br(e($f['help'])) !!}</p>@endif
                @elseif (in_array($f['type'], ['radio', 'checkbox', 'rating'], true))
                    <fieldset>
                        <legend>{{ $f['label'] }}{!! $star !!}</legend>
                        @if ($f['help'])<p class="help">{!! nl2br(e($f['help'])) !!}</p>@endif
                        @if ($f['type'] === 'rating')
                            <div class="rating">@foreach (['1', '2', '3', '4', '5'] as $n)<label><input type="radio" name="{{ $f['id'] }}" value="{{ $n }}" {{ $req }} @checked($checked($f['id'], $n))><span>{{ $n }}</span></label>@endforeach</div>
                        @else
                            @foreach ($f['options'] as $o)
                                <label class="opt"><input type="{{ $f['type'] }}" name="{{ $f['id'] }}{{ $f['type'] === 'checkbox' ? '[]' : '' }}" value="{{ $o }}" {{ $f['type'] === 'radio' ? $req : '' }} @checked($checked($f['id'], $o))> {{ $o }}</label>
                            @endforeach
                        @endif
                    </fieldset>
                @else
                    <div class="q">
                        <label for="{{ $fid }}">{{ $f['label'] }}{!! $star !!}</label>
                        @if ($f['help'])<p class="help">{!! nl2br(e($f['help'])) !!}</p>@endif
                        @if ($f['type'] === 'textarea')
                            <textarea id="{{ $fid }}" name="{{ $f['id'] }}" maxlength="5000" placeholder="{{ $f['placeholder'] }}" {{ $req }}>{{ $val($f['id']) }}</textarea>
                        @elseif ($f['type'] === 'select')
                            <select id="{{ $fid }}" name="{{ $f['id'] }}" {{ $req }}>
                                <option value="">{{ $f['placeholder'] ?: 'Choose…' }}</option>
                                @foreach ($f['options'] as $o)<option value="{{ $o }}" @selected($val($f['id']) === $o)>{{ $o }}</option>@endforeach
                            </select>
                        @else
                            @php($type = ['phone' => 'tel', 'text' => 'text', 'email' => 'email', 'number' => 'number', 'date' => 'date'][$f['type']])
                            <input id="{{ $fid }}" type="{{ $type }}" name="{{ $f['id'] }}" maxlength="500" placeholder="{{ $f['placeholder'] }}" value="{{ $val($f['id']) }}" {{ $req }}
                                @isset($f['min']) min="{{ $f['min'] }}" @endisset @isset($f['max']) max="{{ $f['max'] }}" @endisset
                                @if ($f['type'] === 'email') autocomplete="email" @elseif ($f['type'] === 'phone') autocomplete="tel" @elseif ($f['type'] === 'number') step="any" @endif>
                        @endif
                    </div>
                @endif
            @endforeach
            <button class="btn" type="submit">{{ $s['button'] }}</button>
            <p class="note">Never send passwords or card details through this form.</p>
        </form>
    @endif
</main>
<p class="credit">Made with the <a href="{{ route('form-builder') }}" target="_blank" rel="noopener">free form builder by Owlvix</a> · <a href="{{ $report }}" target="_blank" rel="noopener nofollow">Report</a></p>
</body>
</html>
