@extends('admin.layout')
@section('title', 'Reviews')

@section('content')
    <div class="head">
        <div>
            <h1>Reviews</h1>
            <p class="muted">Client reviews on the homepage and quote popup. Visitors see reviews from their own country first, then this order.</p>
        </div>
        <a href="{{ route('admin.testimonials.create') }}" class="btn">+ Add review</a>
    </div>

    {{-- Fiverr rating summary --}}
    <form method="POST" action="{{ route('admin.testimonials.settings') }}" class="panel summary-form">
        @csrf
        <h2>Fiverr rating <span class="muted">shown above the reviews and in the popup</span></h2>
        @if ($errors->any())
            <div class="alert" role="alert">{{ $errors->first() }}</div>
        @endif
        <div class="summary-form__grid">
            <label>Rating (0–5)
                <input type="number" name="reviews_rating" step="0.1" min="0" max="5" required value="{{ old('reviews_rating', $settings['reviews_rating']) }}">
            </label>
            <label>Total reviews
                <input type="number" name="reviews_count" min="0" required value="{{ old('reviews_count', $settings['reviews_count']) }}">
            </label>
            <label>Five-star reviews
                <input type="number" name="reviews_five_star" min="0" required value="{{ old('reviews_five_star', $settings['reviews_five_star']) }}">
            </label>
            <label class="summary-form__url">Profile link
                <input type="url" name="reviews_profile_url" required value="{{ old('reviews_profile_url', $settings['reviews_profile_url']) }}">
            </label>
        </div>
        <button type="submit" class="btn btn--ghost">Save rating</button>
    </form>

    {{-- Review list --}}
    <section class="panel list">
        @forelse ($testimonials as $t)
            <article class="t-row {{ $t->is_published ? '' : 'is-hidden' }}">
                <div class="t-row__order">
                    <form method="POST" action="{{ route('admin.testimonials.move', [$t, 'up']) }}">@csrf
                        <button type="submit" class="icon-btn" aria-label="Move up" @disabled($loop->first)>▲</button>
                    </form>
                    <form method="POST" action="{{ route('admin.testimonials.move', [$t, 'down']) }}">@csrf
                        <button type="submit" class="icon-btn" aria-label="Move down" @disabled($loop->last)>▼</button>
                    </form>
                </div>
                <div class="t-row__main">
                    <p class="t-row__who">
                        <strong>{{ $t->name }}</strong>
                        <span class="muted">{{ $t->flag() }} {{ $t->countryName() ?: 'No country' }}{{ $t->service ? ' · '.$t->service : '' }}</span>
                        <span class="t-row__stars" aria-label="{{ $t->rating }} stars">{{ str_repeat('★', $t->rating) }}</span>
                    </p>
                    <p class="t-row__text">“{{ \Illuminate\Support\Str::limit($t->text, 180) }}”</p>
                </div>
                <div class="t-row__actions">
                    <form method="POST" action="{{ route('admin.testimonials.toggle', [$t, 'is_published']) }}">@csrf
                        <button type="submit" class="chip {{ $t->is_published ? 'chip--on' : '' }}" title="Show on the website">{{ $t->is_published ? '● On site' : '○ Hidden' }}</button>
                    </form>
                    <form method="POST" action="{{ route('admin.testimonials.toggle', [$t, 'in_email']) }}">@csrf
                        <button type="submit" class="chip {{ $t->in_email ? 'chip--on' : '' }}" title="Show in the confirmation email (up to 3)">{{ $t->in_email ? '✉ In email' : '✉ Not in email' }}</button>
                    </form>
                    <a href="{{ route('admin.testimonials.edit', $t) }}" class="btn btn--ghost btn--sm">Edit</a>
                </div>
            </article>
        @empty
            <p class="empty">No reviews yet. <a href="{{ route('admin.testimonials.create') }}" class="detail__email">Add the first one</a>.</p>
        @endforelse
    </section>
    <p class="muted" style="margin-top:12px">The confirmation email shows up to 3 reviews marked “In email”, in this order.</p>
@endsection
