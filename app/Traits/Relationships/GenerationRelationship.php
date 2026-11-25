<?php

namespace App\Traits\Relationships;

use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait GenerationRelationship
{
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
