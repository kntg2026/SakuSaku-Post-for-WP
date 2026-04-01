<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PostResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'excerpt' => $this->excerpt,
            'status' => $this->status->value,
            'google_doc_url' => $this->google_doc_url,
            'wp_post_id' => $this->wp_post_id,
            'wp_preview_url' => $this->wp_preview_url,
            'wp_permalink' => $this->wp_permalink,
            'poster_comment' => $this->poster_comment,
            'admin_comment' => $this->admin_comment,
            'category' => $this->whenLoaded('category', fn() => [
                'id' => $this->category->id,
                'name' => $this->category->name,
            ]),
            'user' => $this->whenLoaded('user', fn() => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'email' => $this->user->email,
            ]),
            'images_count' => $this->whenCounted('images'),
            'tags' => $this->whenLoaded('tags', fn() =>
                $this->tags->pluck('tag_name')->all()
            ),
            'published_at' => $this->published_at?->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
