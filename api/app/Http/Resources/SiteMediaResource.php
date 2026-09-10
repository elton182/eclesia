<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\SiteMedia
 */
class SiteMediaResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'path' => $this->path,
            'alt' => $this->alt,
            'mime' => $this->mime,
            'url' => $this->url(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
