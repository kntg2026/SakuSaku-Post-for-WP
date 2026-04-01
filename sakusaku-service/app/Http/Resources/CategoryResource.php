<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'wp_category_id' => $this->wp_category_id,
            'parent_id' => $this->parent_id,
            'is_active' => $this->is_active,
            'posts_count' => $this->whenCounted('posts'),
        ];
    }
}
