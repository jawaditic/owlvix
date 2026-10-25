@extends('admin.layout')
@section('title', $testimonial->exists ? 'Edit review' : 'Add review')

@section('content')
    <a href="{{ route('admin.testimonials.index') }}" class="back">← All reviews</a>

    <form method="POST" class="panel detail t-form"
        action="{{ $testimonial->exists ? route('admin.testimonials.update', $testimonial) : route('admin.testimonials.store') }}">
        @csrf
        @if ($testimonial->exists) @method('PUT') @endif
        <h1>{{ $testimonial->exists ? 'Edit review' : 'Add review' }}</h1>
        <p class="muted">Copy the client's words from Fiverr. Their Fiverr username is fine as the name.</p>

        @if ($errors->any())
            <div class="alert" role="alert">{{ $errors->first() }}</div>
        @endif

        <div class="t-form__grid">
            <label>Client name
                <input type="text" name="name" required maxlength="80" value="{{ old('name', $testimonial->name) }}" placeholder="e.g. sukhdev79">
            </label>
            <label>Country
                <select name="country_code">
                    <option value="">— None —</option>
                    @foreach (config('countries') as $code => $country)
                        <option value="{{ $code }}" @selected(old('country_code', $testimonial->country_code) === $code)>{{ $country }}</option>
                    @endforeach
                </select>
            </label>
            <label>Project type
                <input type="text" name="service" maxlength="60" list="services" value="{{ old('service', $testimonial->service) }}" placeholder="e.g. Web app">
                <datalist id="services">
                    @foreach (['Web app', 'Mobile app', 'Cross-platform app', 'AI chatbot', 'AI agent', 'Website', 'Shopify store', 'WordPress', 'Desktop app'] as $s)
                        <option value="{{ $s }}">
                    @endforeach
                </datalist>
            </label>
            <label>Stars
                <select name="rating">
                    @foreach ([5, 4, 3, 2, 1] as $n)
                        <option value="{{ $n }}" @selected((int) old('rating', $testimonial->rating) === $n)>{{ str_repeat('★', $n) }} ({{ $n }})</option>
                    @endforeach
                </select>
            </label>
        </div>

        <label>Review
            <textarea name="text" rows="5" required minlength="5" maxlength="1000">{{ old('text', $testimonial->text) }}</textarea>
        </label>

        <div class="t-form__checks">
            <label class="check"><input type="checkbox" name="is_published" value="1" @checked(old('is_published', $testimonial->is_published))> Show on the website</label>
            <label class="check"><input type="checkbox" name="in_email" value="1" @checked(old('in_email', $testimonial->in_email))> Show in the confirmation email</label>
        </div>

        <div class="detail__actions">
            <button type="submit" class="btn">{{ $testimonial->exists ? 'Save review' : 'Add review' }}</button>
            <a href="{{ route('admin.testimonials.index') }}" class="btn btn--ghost">Cancel</a>
        </div>
    </form>

    @if ($testimonial->exists)
        <form method="POST" action="{{ route('admin.testimonials.destroy', $testimonial) }}" class="t-delete"
            onsubmit="return confirm('Delete this review? This can\'t be undone.')">
            @csrf @method('DELETE')
            <button type="submit" class="btn btn--danger">Delete review</button>
        </form>
    @endif
@endsection
