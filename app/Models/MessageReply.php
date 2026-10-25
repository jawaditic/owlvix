<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MessageReply extends Model
{
    protected $fillable = ['subject', 'body'];

    public function message(): BelongsTo
    {
        return $this->belongsTo(ContactMessage::class, 'contact_message_id');
    }
}
