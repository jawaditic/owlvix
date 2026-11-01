{{-- The owl's invitation to a builder's quick setup wizard (public/js/builder-wizard.js), shown on a first visit until
     the visitor starts it or picks the full builder. Same look as the mobile app builder's banner.
     Params: $say (first owl line), $title (HTML), $sub, $steps (three [line1, line2]), $go (button), $pill, $skip --}}
<div class="wzb" data-wz-banner hidden>
    <div class="wzb__sparks" aria-hidden="true"><i></i><i></i><i></i><i></i><i></i></div>
    <div class="wzb__owl">
        <div class="wzb__bubble" aria-hidden="true"><span data-wzb-say>{{ $say }}</span></div>
        <div class="wzb__perch">@include('partials.live-owl', ['width' => 150, 'variant' => 'dev', 'alt' => 'The Owlvix owl, ready to help you'])</div>
    </div>
    <div class="wzb__text">
        <span class="wzb__pill"><i aria-hidden="true"></i>{{ $pill ?? 'Quick setup · about 2 minutes' }}</span>
        <h2>{!! $title !!}</h2>
        <p>{{ $sub }}</p>
        <ol class="wzb__steps">
            @foreach ($steps as $i => [$a, $b])
                <li><b>{{ $i + 1 }}</b><span>{{ $a }}<br>{{ $b }}</span></li>
            @endforeach
        </ol>
        <div class="wzb__actions">
            <button type="button" class="btn btn--glow wzb__go" data-wz-start>{{ $go }}</button>
            <button type="button" class="wzb__no" data-wzb-no>{{ $skip ?? 'or use the full builder →' }}</button>
        </div>
    </div>
    <div class="wzb__phone" aria-hidden="true">
        <div class="wzb__screen">
            <span class="wzb__status"></span>
            <span class="wzb__hero"><i></i><i></i><em></em></span>
            <span class="wzb__cards"><i></i><i></i></span>
            <span class="wzb__line"></span><span class="wzb__line wzb__line--s"></span>
            <span class="wzb__btn"></span>
            <span class="wzb__nav"><i></i><i></i><i></i></span>
        </div>
    </div>
</div>
