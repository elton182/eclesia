<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\AppSetting;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AppSetting
 */
class AppBrandingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'logo_path' => $this->logo_path,
            'logo_url' => $this->logoUrl(),
            'logo_diocese_path' => $this->logo_diocese_path,
            'logo_diocese_url' => $this->logoDioceseUrl(),
            'cores' => $this->cores,
        ];
    }
}
