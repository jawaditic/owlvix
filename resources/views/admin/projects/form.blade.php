@extends('admin.layout')
@section('title', $project->exists ? 'Edit project' : 'Add project')

@section('content')
    <a href="{{ route('admin.projects.index') }}" class="back">← All projects</a>

    <form method="POST" enctype="multipart/form-data" class="panel detail t-form"
        action="{{ $project->exists ? route('admin.projects.update', $project) : route('admin.projects.store') }}">
        @csrf
        @if ($project->exists) @method('PUT') @endif
        <h1>{{ $project->exists ? 'Edit project' : 'Add project' }}</h1>

        @if ($errors->any())
            <div class="alert" role="alert">{{ $errors->first() }}</div>
        @endif

        <div class="p-form">
            <div class="p-form__image">
                <span class="p-form__label">Image</span>
                <label class="p-drop" id="drop">
                    <img src="{{ $project->imageUrl() }}" alt="" id="preview" @unless ($project->image) hidden @endunless>
                    <span class="p-drop__hint" @if ($project->image) hidden @endif>Click to choose a screenshot or mockup<br><small>JPG, PNG or WebP · up to 5 MB · about 1200px wide is ideal</small></span>
                    <input type="file" name="image" accept="image/jpeg,image/png,image/webp" @unless ($project->exists) required @endunless>
                </label>
                @if ($project->image)<small class="muted">Choose a new file to replace it.</small>@endif
            </div>

            <div class="p-form__fields">
                <div class="t-form__grid p-form__grid">
                    <label>Project name
                        <input type="text" name="title" required maxlength="120" value="{{ old('title', $project->title) }}" placeholder="e.g. Spigen">
                    </label>
                    <label>Category
                        <select name="category">
                            @foreach (\App\Models\Project::CATEGORIES as $key => $label)
                                <option value="{{ $key }}" @selected(old('category', $project->category) === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                </div>
                <label><span>Short description <small class="muted">(optional, shown under the name)</small></span>
                    <input type="text" name="summary" maxlength="300" value="{{ old('summary', $project->summary) }}" placeholder="e.g. Shopify store redesign with custom product filters">
                </label>
                <label>Website link
                    <input type="url" name="url" value="{{ old('url', $project->url) }}" placeholder="https://">
                </label>
                <div class="t-form__grid p-form__grid">
                    <label>App Store link
                        <input type="url" name="ios_url" value="{{ old('ios_url', $project->ios_url) }}" placeholder="https://apps.apple.com/…">
                    </label>
                    <label>Google Play link
                        <input type="url" name="android_url" value="{{ old('android_url', $project->android_url) }}" placeholder="https://play.google.com/…">
                    </label>
                </div>
                <label class="check"><input type="checkbox" name="is_published" value="1" @checked(old('is_published', $project->is_published))> Show on the website</label>
            </div>
        </div>

        <div class="detail__actions">
            <button type="submit" class="btn">{{ $project->exists ? 'Save project' : 'Add project' }}</button>
            <a href="{{ route('admin.projects.index') }}" class="btn btn--ghost">Cancel</a>
        </div>
    </form>

    @if ($project->exists)
        <form method="POST" action="{{ route('admin.projects.destroy', $project) }}" class="t-delete"
            onsubmit="return confirm('Delete this project? This can\'t be undone.')">
            @csrf @method('DELETE')
            <button type="submit" class="btn btn--danger">Delete project</button>
        </form>
    @endif

    <script>
        // Show the chosen image straight away
        document.querySelector('#drop input').addEventListener('change', e => {
            const f = e.target.files[0]; if (!f) return;
            const img = document.getElementById('preview');
            img.src = URL.createObjectURL(f); img.hidden = false;
            document.querySelector('.p-drop__hint').hidden = true;
        });
    </script>
@endsection
