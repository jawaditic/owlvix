{{-- A light inline icon for places that repeat it many times (review stars, card links): the shapes are written once
     per page as SVG <symbol>s, then each copy is a tiny <use>. $n = icon name, $times = how many in a row (one SVG). --}}
@php
    $times = (int) ($times ?? 1);
    $defs = request()->attributes->get('oi-symbols', []);
    $add = '';
    if (! isset($defs[$n])) {
        $add .= preg_replace(['/^<svg class="oi" viewBox="0 0 24 24" width="1em" height="1em"/', '/ aria-hidden="true" focusable="false">/', '/<\/svg>$/'], ['<symbol id="oi-'.$n.'" viewBox="0 0 24 24"', '>', '</symbol>'], \App\Support\Icons::i($n), 1);
        $defs[$n] = true;
    }
    if ($times > 1 && ! isset($defs[$n.'-row'])) {
        $add .= '<symbol id="oi-'.$n.'-row" viewBox="0 0 128 24">'.implode('', array_map(fn ($i) => '<use href="#oi-'.$n.'" x="'.($i * 26).'" width="24" height="24"/>', range(0, 4))).'</symbol>';
        $defs[$n.'-row'] = true;
    }
    request()->attributes->set('oi-symbols', $defs);
    $w = 26 * min($times, 5) - 2;
@endphp
@if ($add !== '')<svg width="0" height="0" style="position:absolute" aria-hidden="true">{!! $add !!}</svg>@endif
@if ($times === 1)<svg class="oi" width="1em" height="1em" aria-hidden="true"><use href="#oi-{{ $n }}"/></svg>@elseif ($times > 1)<svg class="oi" viewBox="0 0 {{ $w }} 24" width="{{ round($w / 24, 2) }}em" height="1em" style="width:{{ round($w / 24 * 1.1, 2) }}em" aria-hidden="true"><use href="#oi-{{ $n }}-row" width="128" height="24"/></svg>@endif