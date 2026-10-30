{{-- The Fiverr rating line used everywhere: "207 of 217 reviews are 5 stars on Fiverr" (Admin → Reviews).
     Fiverr's headline rating (4.5) is an average of category scores, not of the stars, so it isn't shown next to it. --}}
@if (($t['five_star'] ?? 0) > 0 && ($t['count'] ?? 0) >= $t['five_star'])
<strong>{{ $t['five_star'] }} of {{ $t['count'] }}</strong> reviews are 5 stars on Fiverr
@else
<strong>{{ $t['count'] }}</strong> reviews on Fiverr
@endif
