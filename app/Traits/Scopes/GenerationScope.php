<?php

namespace App\Traits\Scopes;

use Illuminate\Database\Eloquent\Builder;

trait GenerationScope
{
    /**
     * @param  Builder<\App\Models\Generation>  $query
     * @return Builder<\App\Models\Generation>
     */
    public function scopeFinished(Builder $query, int $userId): Builder
    {
        return $query
            ->where('user_id', $userId)
            ->whereIn('kind', ['post', 'chat_post'])
            ->where('status', 'done')
            ->latest('finished_at')
            ->latest('id');
    }
}
