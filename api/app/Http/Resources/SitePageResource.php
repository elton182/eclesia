<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\SitePage
 */
class SitePageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'titulo' => $this->titulo,
            'status' => $this->status,
            'is_home' => (bool) $this->is_home,
            'ordem' => $this->ordem,
            'mostrar_no_menu' => (bool) $this->mostrar_no_menu,
            'seo' => $this->seo,
            'blocks' => SiteBlockResource::collection($this->whenLoaded('blocks')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
