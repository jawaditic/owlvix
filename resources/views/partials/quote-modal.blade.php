{{-- Free quote popup: opens once per visit; the owl says a short intro. --}}
<dialog class="quote-modal" id="quote-modal" aria-labelledby="quote-title">
    <button type="button" class="quote-modal__close" aria-label="Close" data-close>
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg>
    </button>

    <div class="quote-modal__grid">
        <div class="quote-owl">
            <button type="button" class="sound-hint">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 9v6h4l5 4V5L8 9H4z" class="fill"/><path d="M16 8.5a5 5 0 0 1 0 7M18.5 6a8.5 8.5 0 0 1 0 12"/></svg>
                <span>Tap for sound</span>
            </button>

            {{-- What we build + what clients say (fills the space above the owl on wider screens) --}}
            @php($t = \App\Support\Testimonials::data())
            <div class="quote-proof">
                <a href="{{ $t['profile_url'] }}" class="quote-proof__rating" target="_blank" rel="noopener">
                    <span aria-hidden="true">★★★★★</span> <strong>{{ $t['rating'] }}</strong> · {{ $t['count'] }} reviews on Fiverr
                </a>
                <ul class="quote-proof__services" aria-label="What we build">
                    @foreach (['Web apps', 'Mobile apps', 'AI chatbots', 'AI agents', 'Websites', 'Shopify'] as $svc)
                        <li>{{ $svc }}</li>
                    @endforeach
                </ul>
                @if (count($t['reviews']))
                <div class="quote-proof__reviews is-loading" data-rotate-reviews data-reviews aria-busy="true">
                    @foreach ($t['reviews'] as $r)
                        <figure class="quote-proof__review" data-country="{{ $r['code'] }}" @if (! $loop->first) hidden @endif>
                            <blockquote>“{{ \Illuminate\Support\Str::words($r['text'], 30, '…') }}”</blockquote>
                            <figcaption>{{ $r['flag'] }} <strong>{{ $r['name'] }}</strong>@if ($r['country']) · {{ $r['country'] }}@endif</figcaption>
                        </figure>
                    @endforeach
                </div>
                @endif
            </div>
            <div class="say">
                <p class="say__text" aria-live="polite" data-owl-text>Tell me about your project, and get a free quote within one day!</p>
            </div>
            <div class="owl-wrap owl-wrap--static">
                <div class="owl-flip">
                    @include('partials.owl', ['alt' => 'Owlvix owl'])
                </div>
            </div>
            <button type="button" class="board__play quote-owl__play" data-owl-play>
                <svg class="i-play" viewBox="0 0 24 24" aria-hidden="true"><path d="M8 5v14l11-7z"/></svg>
                <svg class="i-stop" viewBox="0 0 24 24" aria-hidden="true"><path d="M7 7h10v10H7z"/></svg>
                <span>Hear the owl</span>
            </button>
        </div>

        <form class="form quote-form" method="POST" action="{{ route('contact.store') }}#contact" novalidate data-validate>
            @csrf
            <p class="eyebrow">Free quote</p>
            <h2 id="quote-title" tabindex="-1" autofocus>Get a free quote</h2>
            <p class="quote-form__sub">Takes 30 seconds. No obligation.</p>

            <div class="hp" aria-hidden="true">
                <label>Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label>
            </div>
            <input type="hidden" name="budget" value="Not sure yet">
            <input type="hidden" name="source" value="popup">

            <div class="row">
                <label>Name
                    <input type="text" name="name" required maxlength="100" autocomplete="name">
                </label>
                <label>Email
                    <input type="email" name="email" required maxlength="150" autocomplete="email">
                </label>
            </div>
            <div class="row">
                {{-- Not wrapped in <label>: the country picker inside has its own buttons --}}
                <div class="field">
                    <label for="quote-phone">Phone <span class="optional">(optional)</span></label>
                    <input type="tel" id="quote-phone" name="phone" maxlength="30" autocomplete="tel" inputmode="tel" data-intl-phone>
                </div>
                <label>What do you need?
                    <select name="service">
                        @foreach (['Web app', 'Mobile app', 'AI chatbot / agent', 'Website', 'Monthly developer', 'Something else'] as $opt)
                            <option value="{{ $opt }}">{{ $opt }}</option>
                        @endforeach
                    </select>
                </label>
            </div>
            {{-- site.js turns this textarea into a rich text editor (and keeps it in sync for sending) --}}
            <div class="field">
                <label for="quote-message">Your project</label>
                <textarea id="quote-message" name="message" rows="3" required placeholder="A few lines about what you want built" data-rich></textarea>
            </div>
            <button type="submit" class="btn btn--block">Get my free quote</button>
            <button type="button" class="quote-form__later" data-close>Maybe later</button>
        </form>
    </div>
</dialog>
