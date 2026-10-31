<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A push notification an owner sent to everyone with their app (AppBuilderController::push). */
class BuilderAppPush extends Model
{
    protected $fillable = ['title', 'body', 'tab', 'status', 'error'];

    protected function casts(): array
    {
        return ['tab' => 'integer'];
    }

    public function toApi(): array
    {
        return ['id' => $this->id, 'title' => $this->title, 'body' => $this->body, 'tab' => $this->tab, 'status' => $this->status, 'sent_at' => $this->created_at?->toIso8601String()];
    }
}
