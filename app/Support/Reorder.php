<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/** Move one item up or down in a sorted list (portfolio projects, reviews). */
class Reorder
{
    /** @param Collection<int, Model> $ordered all items in their current order */
    public static function move(Collection $ordered, Model $item, string $direction): void
    {
        $all = $ordered->values();
        $i = $all->search(fn ($m) => $m->is($item));
        $j = $direction === 'up' ? $i - 1 : $i + 1;
        if ($i === false || $j < 0 || $j >= $all->count()) {
            return;
        }
        [$all[$i], $all[$j]] = [$all[$j], $all[$i]];
        $all->each(fn ($m, $n) => $m->forceFill(['sort_order' => $n + 1])->save());
    }
}
