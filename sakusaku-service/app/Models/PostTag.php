<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PostTag extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'post_id', 'tenant_id', 'tag_name', 'wp_tag_id', 'score',
    ];

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }
}
