{{-- Site footer, shared by every public page. $top: where the logo links (default: top of this page). --}}
<footer class="footer">
    <div class="container footer__inner">
        <a href="{{ $top ?? '#top' }}" class="brand brand--sm" aria-label="Owlvix home">
            <img src="{{ asset('images/logo-mark.svg') }}" alt="Owlvix logo" width="28" height="28">
            <span>Owl<span class="accent">vix</span></span>
        </a>
        <p>© {{ date('Y') }} Owlvix. Web, mobile & AI development.</p>
        <nav class="footer__links" aria-label="Footer">
            @unless (request()->routeIs('home'))<a href="{{ route('home') }}">Home</a>@endunless
            @unless (request()->routeIs('landing'))<a href="{{ route('landing') }}">Services</a>@endunless
            @unless (request()->routeIs('about'))<a href="{{ route('about') }}">About</a>@endunless
            @unless (request()->routeIs('speed-test'))<a href="{{ route('speed-test') }}">Free speed test</a>@endunless
            @unless (request()->routeIs('seo-checker'))<a href="{{ route('seo-checker') }}">SEO checker</a>@endunless
            <a href="{{ route('privacy') }}">Privacy</a>
            <a href="{{ request()->routeIs('home', 'landing', 'service.*') ? '#contact' : route('landing').'#contact' }}">Start a project →</a>
        </nav>
    </div>
</footer>
