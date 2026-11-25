<?php

namespace App\Models;

use App\Traits\Relationships\MessageRelationship;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['conversation_id', 'role', 'content', 'image_prompt'])]
class Message extends Model
{
    use MessageRelationship;

    protected $touches = ['conversation'];
}
