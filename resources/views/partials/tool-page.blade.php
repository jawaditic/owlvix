{{-- Shared top of a website tool page: breadcrumb, pill, heading, lead, the URL form with a perched owl,
     and the loading / error areas. Params: name (breadcrumb), h1 (HTML), lead, action (route), icon, button;
     optional: placeholder, label (for screen readers), wait (loading time text). --}}
<section class="hero speed-hero">
    <div class="container container--narrow">
        <nav class="crumbs" aria-label="Breadcrumb">
            <a href="{{ route('home') }}">Home</a><span aria-hidden="true">›</span><a href="{{ route('free-tools') }}">Free tools</a><span aria-hidden="true">›</span><span aria-current="page">{{ $name }}</span>
        </nav>
        <span class="pill"><span class="pill__dot" aria-hidden="true"></span>Free tool · No sign-up</span>
        <h1>{!! $h1 !!}</h1>
        <p class="lead">{{ $lead }}</p>

        <form class="speed-form seo-form card" data-tool-form data-live action="{{ $action }}" method="POST" novalidate>
            <span class="perch-owl" aria-hidden="true">@include('partials.live-owl', ['width' => 96, 'variant' => 'dev', 'alt' => 'Owlvix owl checking websites'])</span>
            @csrf
            <label class="speed-form__url">
                <span class="sr-only">{{ $label ?? 'Website address' }}</span>
                <span class="speed-form__icon" aria-hidden="true">{!! \App\Support\Icons::svg($icon) !!}</span>
                <input type="text" name="url" inputmode="url" autocomplete="url" placeholder="{{ $placeholder ?? 'example.com' }}" value="{{ is_string(request('url')) ? request('url') : '' }}" required maxlength="500">
            </label>
            <button type="submit" class="btn btn--glow">{{ $button }}
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
            </button>
        </form>
        <p class="speed-error" role="alert" hidden data-tool-error></p>
    </div>
</section>

<section class="speed-loading" hidden data-tool-loading>
    <div class="container container--narrow">
        <div class="card speed-loading__card">
            @include('partials.live-owl', ['width' => 140, 'variant' => 'dev', 'alt' => 'Owlvix owl at work'])
            <div>
                <strong>Checking…</strong>
                <p data-tool-step></p>
                <div class="speed-loading__bar"><span></span></div>
                <small>{{ $wait ?? 'This usually takes 5–15 seconds.' }}</small>
            </div>
        </div>
    </div>
</section>
