{{-- The animated vector owl at any size: breathes, blinks, glances around and sways (owl.js idle mode).
     Params: width (px), class (extra classes), alt, variant ('dev' = headset + laptop). --}}
<span class="live-owl {{ $class ?? '' }}" data-owl-idle style="--owl-w: {{ $width ?? 120 }}px">
    @include('partials.owl', ['alt' => $alt ?? 'Owlvix owl mascot', 'variant' => $variant ?? 'teacher'])
</span>
