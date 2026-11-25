<?php

namespace App\Traits\Relationships;

use App\Models\Conversation;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait MessageRelationship
{
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }
}
