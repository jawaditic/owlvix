{{-- FAQ block for the free tool pages, plus links to the other tools. --}}
<section class="section section--alt speed-info">
    <div class="container container--narrow">
        <p class="eyebrow">Good to know</p>
        <h2>{{ $title }}</h2>
        <div class="faq">
            @foreach ($faq as [$q, $a])
                <details><summary>{{ $q }}</summary><p>{{ $a }}</p></details>
            @endforeach
        </div>
        <p class="speed-info__more">More free tools: <a href="{{ route('free-tools') }}">see all free tools</a>.</p>
    </div>
</section>
