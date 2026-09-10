<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\SiteFormSubmission
 */
class SiteFormSubmissionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'site_form_id' => $this->site_form_id,
            'values' => $this->payloadArray(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
