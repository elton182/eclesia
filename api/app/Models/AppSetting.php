<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\URL;

class AppSetting extends Model
{
    protected $table = 'app_settings';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'logo_path',
        'logo_diocese_path',
        'cores',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'cores' => 'array',
        ];
    }

    public function logoUrl(): ?string
    {
        return $this->signedLogoUrl($this->logo_path, 'app.branding.logo.show');
    }

    public function logoDioceseUrl(): ?string
    {
        return $this->signedLogoUrl($this->logo_diocese_path, 'app.branding.logo-diocese.show');
    }

    private function signedLogoUrl(?string $path, string $routeName): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }

        $tenantSlug = tenant('slug');
        if (! is_string($tenantSlug) || $tenantSlug === '') {
            return null;
        }

        return URL::temporarySignedRoute(
            $routeName,
            now()->addHours(12),
            ['tenant' => $tenantSlug],
        );
    }
}
