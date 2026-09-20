<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AppSetting;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class AppBrandingService
{
    public function settings(): AppSetting
    {
        return AppSetting::query()->firstOrCreate([], [
            'logo_path' => null,
            'cores' => null,
        ]);
    }

    /**
     * @param  array{cores?: array<string, string>|null}  $data
     */
    public function update(array $data): AppSetting
    {
        $settings = $this->settings();

        if (array_key_exists('cores', $data)) {
            $settings->cores = $data['cores'];
        }

        $settings->save();

        return $settings->refresh();
    }

    public function storeLogo(UploadedFile $file): AppSetting
    {
        $settings = $this->settings();
        $this->deleteLogoFile($settings->logo_path);
        $path = $file->store('branding', 'public');
        $settings->update(['logo_path' => $path]);

        return $settings->refresh();
    }

    public function destroyLogo(): AppSetting
    {
        $settings = $this->settings();
        $this->deleteLogoFile($settings->logo_path);
        $settings->update(['logo_path' => null]);

        return $settings->refresh();
    }

    public function storeLogoDiocese(UploadedFile $file): AppSetting
    {
        $settings = $this->settings();
        $this->deleteLogoFile($settings->logo_diocese_path);
        $path = $file->store('branding', 'public');
        $settings->update(['logo_diocese_path' => $path]);

        return $settings->refresh();
    }

    public function destroyLogoDiocese(): AppSetting
    {
        $settings = $this->settings();
        $this->deleteLogoFile($settings->logo_diocese_path);
        $settings->update(['logo_diocese_path' => null]);

        return $settings->refresh();
    }

    public function deleteLogoFile(?string $path): void
    {
        if ($path === null || $path === '') {
            return;
        }

        if (Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }

    /**
     * @return array{logo_path: ?string, logo_url: ?string, logo_diocese_path: ?string, logo_diocese_url: ?string, cores: ?array<string, string>}
     */
    public function brandingPayload(): array
    {
        $settings = AppSetting::query()->first();

        return [
            'logo_path' => $settings?->logo_path,
            'logo_url' => $settings?->logoUrl(),
            'logo_diocese_path' => $settings?->logo_diocese_path,
            'logo_diocese_url' => $settings?->logoDioceseUrl(),
            'cores' => $settings?->cores,
        ];
    }
}
