{{-- Quote form used at the top of the landing page and the service pages.
     $service: preselects "What do you need?"  $title: heading (default "Get your free quote").
     Leads are saved with source "landing". --}}
<form class="card form lp-form" id="quote-form" data-clarity-mask="true" method="POST" action="{{ route('contact.store') }}" novalidate data-validate>
    @csrf
    <input type="hidden" name="source" value="landing">
    <div>
        <h2 class="lp-form__title">{{ $title ?? 'Get your free quote' }}</h2>
        <p class="lp-form__note">Reply within one business day · No obligation</p>
    </div>
    @if (session('sent'))
        <div class="alert alert--ok" role="status">Thanks! Your message is in. We'll get back to you within one business day.</div>
    @endif
    @if ($errors->has('captcha'))
        <div class="alert alert--err" role="alert">{{ $errors->first('captcha') }}</div>
    @elseif ($errors->any())
        <div class="alert alert--err" role="alert">Please check the highlighted fields.</div>
    @endif

    <div class="hp" aria-hidden="true">
        <label>Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label>
    </div>

    <div class="row">
        <label>Name
            <input type="text" name="name" value="{{ old('name') }}" required maxlength="100" autocomplete="name" @error('name') aria-invalid="true" @enderror>
            @error('name')<small>{{ $message }}</small>@enderror
        </label>
        <label>Email
            <input type="email" name="email" value="{{ old('email') }}" required maxlength="150" autocomplete="email" @error('email') aria-invalid="true" @enderror>
            @error('email')<small>{{ $message }}</small>@enderror
        </label>
    </div>
    <div class="row">
        <label>What do you need?
            <select name="service" id="service">
                @foreach (\App\Support\Enquiries::SERVICES as $opt)
                    <option value="{{ $opt }}" @selected(old('service', $service ?? null) === $opt)>{{ $opt }}</option>
                @endforeach
            </select>
        </label>
        <label>Budget
            <select name="budget">
                @foreach (['Not sure yet', 'Under $500', '$500 – $2,000', '$2,000 – $5,000', '$5,000+'] as $opt)
                    <option value="{{ $opt }}" @selected(old('budget') === $opt)>{{ $opt }}</option>
                @endforeach
            </select>
        </label>
    </div>
    <div class="field">
        <label for="contact-message">Your project</label>
        <textarea id="contact-message" name="message" rows="4" required placeholder="What should it do? Who is it for? Any deadline?" data-rich @error('message') aria-invalid="true" @enderror>{{ old('message') }}</textarea>
        @error('message')<small>{{ $message }}</small>@enderror
    </div>
    <button type="submit" class="btn btn--block btn--glow">Get my free quote</button>
    <p class="form__privacy">We only use your details to reply to you. <a href="{{ route('privacy') }}">Privacy policy</a></p>
</form>
