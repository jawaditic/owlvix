{{-- Client reviews section, shared by the homepage and the landing page --}}
@php($t = \App\Support\Testimonials::data())
@if (count($t['reviews']))
<section class="section" id="reviews">
    <div class="container">
        <p class="eyebrow">Client reviews</p>
        <h2>What clients say</h2>
        <a href="{{ $t['profile_url'] }}" class="rating-badge" target="_blank" rel="noopener">
            <span class="rating-badge__stars" aria-hidden="true">★★★★★</span>
            <span><strong>{{ $t['rating'] }}</strong> on Fiverr · {{ $t['count'] }} reviews · {{ $t['five_star'] }} five-star</span>
        </a>
        <div class="reviews is-loading" data-reviews aria-busy="true">
            @foreach ($t['reviews'] as $r)
                <figure class="review" data-country="{{ $r['code'] }}">
                    <span class="review__stars" aria-label="{{ $r['rating'] }} out of 5 stars">{{ str_repeat('★', $r['rating']) }}<span class="review__stars-off">{{ str_repeat('★', 5 - $r['rating']) }}</span></span>
                    <blockquote>“{{ $r['text'] }}”</blockquote>
                    <figcaption>
                        <span class="review__avatar" aria-hidden="true">{{ strtoupper(substr($r['name'], 0, 1)) }}</span>
                        <span>
                            <strong>{{ $r['name'] }}</strong>
                            <small>{{ $r['flag'] }} {{ collect([$r['country'], $r['service']])->filter()->implode(' · ') }}</small>
                        </span>
                    </figcaption>
                </figure>
            @endforeach
        </div>
        <p class="reviews__more">
            <a href="{{ $t['profile_url'] }}" class="btn btn--ghost" target="_blank" rel="noopener">Read all {{ $t['count'] }} reviews on Fiverr
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 17L17 7M9 7h8v8"/></svg>
            </a>
        </p>
    </div>
</section>

@endif
