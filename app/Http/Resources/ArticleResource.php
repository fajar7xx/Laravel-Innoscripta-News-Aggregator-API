<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ArticleResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'source_id' => $this->source_id,
            'external_id' => $this->external_id,
            'title' => $this->title,
            'description' => $this->description,
            'content' => $this->content,
            'author' => $this->author,
            'url' => $this->url,
            'image_url' => $this->image_url,
            'published_at' => $this->published_at,
            'fetched_at' => $this->fetched_at,

            'source' => new SourceResource($this->whenLoaded('source')),
            'categories' => CategoryResource::collection($this->whenLoaded('categories')),

            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
