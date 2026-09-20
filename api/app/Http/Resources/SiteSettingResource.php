<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Services\AppBrandingService;
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
        $branding = app(AppBrandingService::class)->brandingPayload();
        $tenantName = tenant('name');
        $titulo = is_string($tenantName) && $tenantName !== ''
            ? $tenantName
            : 'Site';

        return [
            'publicado' => (bool) $this->publicado,
            'titulo' => $titulo,
            'logo_path' => $branding['logo_path'],
            'logo_url' => $branding['logo_url'],
            'cores' => $branding['cores'],
            'seo' => $this->seo ?? [],
            'contato' => $this->contato ?? [],
            'menu' => $this->menu ?? [],
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
