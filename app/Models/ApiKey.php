<?php

namespace App\Models;

use App\Traits\Relationships\ApiKeyRelationship;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['user_id', 'name', 'api_key', 'model'])]
#[Hidden(['api_key'])]
class ApiKey extends Model
{
    use ApiKeyRelationship;

    public function resolveRouteBinding($value, $field = null): ?Model
    {
        return $this->newQuery()
            ->where($field ?? $this->getRouteKeyName(), $value)
            ->where('user_id', auth()->id())
            ->first();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'api_key' => 'encrypted',
        ];
    }
}
