{{-- An image with a smaller WebP version when one exists next to it, e.g. images/owl.png → images/owl.240w.webp,
     images/owl.520w.webp (resized copies, picked by display width) or images/portfolio/x.jpg → x.webp.
     The original stays as the fallback. Params: src (public path), alt, width, height, class, loading. --}}
@if (! empty($src))
@php
    $base = preg_replace('/\.(png|jpe?g)$/i', '', $src);
    $sources = [];
    foreach (glob(public_path($base).'.*w.webp') ?: [] as $file) {
        if (preg_match('/\.(\d+)w\.webp$/', $file, $m)) {
            $sources[(int) $m[1]] = asset($base.'.'.$m[1].'w.webp').' '.$m[1].'w';
        }
    }
    ksort($sources);
    if (! $sources && is_file(public_path($base.'.webp'))) {
        $sources[] = asset($base.'.webp');
    }
@endphp
<picture>
    @if ($sources)<source type="image/webp" srcset="{{ implode(', ', $sources) }}" @if (count($sources) > 1) sizes="{{ $width }}px" @endif>@endif
    <img src="{{ asset($src) }}" alt="{{ $alt }}" width="{{ $width }}" height="{{ $height }}" @if (! empty($class)) class="{{ $class }}" @endif @if (! empty($loading)) loading="{{ $loading }}" decoding="async" @endif>
</picture>
@endif
