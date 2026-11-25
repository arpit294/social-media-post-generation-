<?php

namespace App\Models;

use App\Traits\Relationships\ConversationRelationship;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['user_id', 'title'])]
class Conversation extends Model
{
    use ConversationRelationship;

    public function resolveRouteBinding($value, $field = null): ?Model
    {
        return $this->newQuery()
            ->where($field ?? $this->getRouteKeyName(), $value)
            ->where('user_id', auth()->id())
            ->first();
    }
}
