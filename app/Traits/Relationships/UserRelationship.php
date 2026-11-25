<?php

namespace App\Traits\Relationships;

use App\Models\ApiKey;
use App\Models\Conversation;
use App\Models\KnowledgeBase;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

trait UserRelationship
{
    public function knowledgeBase(): HasOne
    {
        return $this->hasOne(KnowledgeBase::class);
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }

    public function apiKeys(): HasMany
    {
        return $this->hasMany(ApiKey::class);
    }

    public function activeApiKey(): BelongsTo
    {
        return $this->belongsTo(ApiKey::class, 'active_api_key_id');
    }
}
