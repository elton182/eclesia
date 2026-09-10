<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\SiteSetting
 */
class SiteSettingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'publicado' => (bool) $this->publicado,
            'titulo' => $this->titulo,
            'subtitulo' => $this->subtitulo,
            'logo_path' => $this->logo_path,
            'favicon_path' => $this->favicon_path,
            'cores' => $this->cores ?? [],
            'seo' => $this->seo ?? [],
            'contato' => $this->contato ?? [],
            'menu' => $this->menu ?? [],
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
