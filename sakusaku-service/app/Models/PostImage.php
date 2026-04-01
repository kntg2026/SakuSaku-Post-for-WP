<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PostImage extends Model
{
    protected $fillable = [
        'post_id', 'tenant_id', 'original_url', 'stored_path',
        'wp_attachment_id', 'wp_url', 'width', 'height',
        'file_size', 'mime_type', 'is_featured', 'sort_order',
        'status', 'error_message',
    ];

    protected function casts(): array
    {
        return [
            'is_featured' => 'boolean',
        ];
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }
}
