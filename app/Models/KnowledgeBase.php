<?php

namespace App\Models;

use App\Traits\Relationships\KnowledgeBaseRelationship;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['user_id', 'body'])]
class KnowledgeBase extends Model
{
    use KnowledgeBaseRelationship;
}
