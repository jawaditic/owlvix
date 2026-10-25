@extends('admin.layout')
@section('title', 'Portfolio')

@section('content')
    <div class="head">
        <div>
            <h1>Portfolio</h1>
            <p class="muted">Projects in “Our work” on the homepage, in this order. The first 8 show straight away; the rest appear with “Show all”.</p>
        </div>
        <a href="{{ route('admin.projects.create') }}" class="btn">+ Add project</a>
    </div>

    <form method="GET" class="filters">
        <select name="category" onchange="this.form.submit()">
            <option value="">All categories ({{ $counts->sum() }})</option>
            @foreach (\App\Models\Project::CATEGORIES as $key => $label)
                <option value="{{ $key }}" @selected(request('category') === $key)>{{ $label }} ({{ $counts[$key] ?? 0 }})</option>
            @endforeach
        </select>
    </form>

    <section class="panel list">
        @forelse ($projects as $p)
            <article class="t-row p-row {{ $p->is_published ? '' : 'is-hidden' }}">
                <div class="t-row__order">
                    <form method="POST" action="{{ route('admin.projects.move', [$p, 'up']) }}">@csrf
                        <button type="submit" class="icon-btn" aria-label="Move up" @disabled($loop->first || request('category'))>▲</button>
                    </form>
                    <form method="POST" action="{{ route('admin.projects.move', [$p, 'down']) }}">@csrf
                        <button type="submit" class="icon-btn" aria-label="Move down" @disabled($loop->last || request('category'))>▼</button>
                    </form>
                </div>
                <img src="{{ $p->imageUrl() }}" alt="" class="p-row__thumb" loading="lazy">
                <div class="t-row__main">
                    <p class="t-row__who"><strong>{{ $p->title }}</strong> <span class="muted">{{ $p->categoryLabel() }}</span></p>
                    <p class="p-row__links">
                        @if ($p->url)<a href="{{ $p->url }}" target="_blank" rel="noopener">Website ↗</a>@endif
                        @if ($p->ios_url)<a href="{{ $p->ios_url }}" target="_blank" rel="noopener">App Store ↗</a>@endif
                        @if ($p->android_url)<a href="{{ $p->android_url }}" target="_blank" rel="noopener">Google Play ↗</a>@endif
                        @unless ($p->url || $p->ios_url || $p->android_url)<span class="muted">No link</span>@endunless
                    </p>
                </div>
                <div class="t-row__actions">
                    <form method="POST" action="{{ route('admin.projects.toggle', $p) }}">@csrf
                        <button type="submit" class="chip {{ $p->is_published ? 'chip--on' : '' }}" title="Show on the website">{{ $p->is_published ? '● On site' : '○ Hidden' }}</button>
                    </form>
                    <a href="{{ route('admin.projects.edit', $p) }}" class="btn btn--ghost btn--sm">Edit</a>
                </div>
            </article>
        @empty
            <p class="empty">No projects here yet.</p>
        @endforelse
    </section>
    @if (request('category'))
        <p class="muted" style="margin-top:12px">To change the order, show all categories.</p>
    @endif
@endsection
