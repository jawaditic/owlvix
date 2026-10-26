<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One answer to the "What stopped you from getting a quote today?" exit survey. */
class SurveyResponse extends Model
{
    /** Answer keys and the words shown to visitors (the order they appear in). */
    public const REASONS = [
        'browsing' => 'Just looking around',
        'price' => 'Not sure about the price',
        'info' => 'Need more information',
        'later' => "I'll come back later",
        'other' => 'Other',
    ];

    protected $fillable = ['reason', 'comment', 'email', 'page', 'device'];

    protected function casts(): array
    {
        return ['read_at' => 'datetime'];
    }

    public function reasonLabel(): string
    {
        return self::REASONS[$this->reason] ?? ucfirst($this->reason);
    }
}
