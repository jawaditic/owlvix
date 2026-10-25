{{-- Free quote popup: opens once per visit; the owl says a short intro. --}}
<dialog class="quote-modal" id="quote-modal" aria-labelledby="quote-title">
    <button type="button" class="quote-modal__close" aria-label="Close" data-close>
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg>
    </button>

    <div class="quote-modal__grid">
        <div class="quote-owl">
            <button type="button" class="sound-hint" aria-label="Turn on the owl's voice">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 9v6h4l5 4V5L8 9H4z" class="fill"/><path d="M16 8.5a5 5 0 0 1 0 7M18.5 6a8.5 8.5 0 0 1 0 12"/></svg>
                <span>Tap for sound</span>
            </button>
            <div class="say">
                <p class="say__text" aria-live="polite" data-owl-text>Tell me about your project, and get a free quote within one day!</p>
            </div>
            <div class="owl-wrap owl-wrap--static">
                <div class="owl-flip">
                    <img src="{{ asset('images/owl-body.png') }}" alt="Owlvix owl" width="395" height="400" class="owl">
                    <img src="{{ asset('images/owl-mouth.png') }}" alt="" width="63" height="43" class="owl__mouth" aria-hidden="true">
                    <svg class="owl__smile" viewBox="0 0 395 400" aria-hidden="true"><path d="M255 187 Q281 196 309 182"/></svg>
                </div>
            </div>
            <button type="button" class="board__play quote-owl__play" aria-label="Play the owl's intro" data-owl-play>
                <svg class="i-play" viewBox="0 0 24 24" aria-hidden="true"><path d="M8 5v14l11-7z"/></svg>
                <svg class="i-stop" viewBox="0 0 24 24" aria-hidden="true"><path d="M7 7h10v10H7z"/></svg>
                <span>Hear the owl</span>
            </button>
        </div>

        <form class="form quote-form" method="POST" action="{{ route('contact.store') }}#contact">
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
            <label>What do you need?
                <select name="service">
                    @foreach (['Web app', 'Mobile app', 'AI chatbot / agent', 'Website', 'Monthly developer', 'Something else'] as $opt)
                        <option value="{{ $opt }}">{{ $opt }}</option>
                    @endforeach
                </select>
            </label>
            <label>Your project
                <textarea name="message" rows="3" required minlength="10" maxlength="5000" placeholder="A few lines about what you want built"></textarea>
            </label>
            <button type="submit" class="btn btn--block">Get my free quote</button>
            <button type="button" class="quote-form__later" data-close>Maybe later</button>
        </form>
    </div>
</dialog>
