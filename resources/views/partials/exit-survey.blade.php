{{-- "Before you go" survey: shown once when a visitor seems about to leave without asking for a quote.
     site.js decides when (exit intent, once per 30 days, never after an enquiry) and sends the answer to /survey. --}}
<dialog class="quote-modal exit-survey" id="exit-survey" aria-labelledby="exit-title">
    <button type="button" class="quote-modal__close" aria-label="Close" data-close>
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg>
    </button>

    <form class="form exit-survey__form" data-survey data-live action="{{ route('survey.store') }}" method="POST" novalidate>
        @csrf
        <div class="exit-survey__head">
            @include('partials.live-owl', ['width' => 64, 'alt' => 'Owlvix owl'])
            <div>
                <p class="eyebrow">Before you go</p>
                <h2 id="exit-title" tabindex="-1" autofocus>What stopped you from getting a quote today?</h2>
            </div>
        </div>

        <div class="hp" aria-hidden="true">
            <label>Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label>
        </div>

        <fieldset class="exit-survey__options">
            <legend class="exit-survey__legend">Pick one</legend>
            @foreach (\App\Models\SurveyResponse::REASONS as $key => $label)
                <label class="exit-survey__option">
                    <input type="radio" name="reason" value="{{ $key }}">
                    <span>{{ $label }}</span>
                </label>
            @endforeach
        </fieldset>

        <div class="exit-survey__more" data-survey-more hidden>
            <label>
                <span data-survey-comment-label>Anything else you'd like to tell us? <span class="optional">(optional)</span></span>
                <textarea name="comment" rows="3" maxlength="1000"></textarea>
            </label>
            <label>
                <span>Your email <span class="optional">(optional, only if you'd like a reply)</span></span>
                <input type="email" name="email" maxlength="150" autocomplete="email">
            </label>
        </div>

        <p class="alert alert--err" role="alert" data-survey-error hidden></p>
        <button type="submit" class="btn btn--block" disabled>Send</button>
        <button type="button" class="quote-form__later" data-close>No thanks</button>
    </form>

    <div class="exit-survey__done" data-survey-done hidden>
        @include('partials.live-owl', ['width' => 96, 'alt' => 'Owlvix owl'])
        <h2 tabindex="-1">Thank you, that really helps!</h2>
        <p class="muted" data-survey-done-text>We read every answer.</p>
        <button type="button" class="btn btn--block" data-survey-quote>Get a free quote</button>
        <button type="button" class="quote-form__later" data-close>Close</button>
    </div>
</dialog>
