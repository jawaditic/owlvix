{{-- "Read our guide" link under a builder's hero, when that builder has a guide (App\Support\Guides). --}}
@if ($guide = \App\Support\Guides::forBuilder(request()->route()?->getName()))
<p class="lpb-guide-link">{{ $guide[1] }} <a href="{{ route($guide[0]) }}">Read our free step-by-step guide →</a></p>
@endif
