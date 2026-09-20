<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAppBrandingLogoRequest;
use App\Http\Requests\UpdateAppBrandingRequest;
use App\Http\Resources\AppBrandingResource;
use App\Models\AppSetting;
use App\Models\SuperAdmin;
use App\Models\User;
use App\Services\AppBrandingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AppBrandingController extends Controller
{
    public function __construct(
        private readonly AppBrandingService $branding,
    ) {}

    public function show(): JsonResponse
    {
        $settings = $this->branding->settings();
        $settings->wasRecentlyCreated = false;

        return (new AppBrandingResource($settings))->response();
    }

    public function update(UpdateAppBrandingRequest $request): JsonResponse
    {
        $settings = $this->branding->update($request->validated());
        $settings->wasRecentlyCreated = false;

        return (new AppBrandingResource($settings))->response();
    }

    public function storeLogo(StoreAppBrandingLogoRequest $request): JsonResponse
    {
        $settings = $this->branding->storeLogo($request->file('file'));
        $settings->wasRecentlyCreated = false;

        return (new AppBrandingResource($settings))->response();
    }

    public function destroyLogo(): Response
    {
        $this->authorizeManage();
        $this->branding->destroyLogo();

        return response()->noContent();
    }

    public function storeLogoDiocese(StoreAppBrandingLogoRequest $request): JsonResponse
    {
        $settings = $this->branding->storeLogoDiocese($request->file('file'));
        $settings->wasRecentlyCreated = false;

        return (new AppBrandingResource($settings))->response();
    }

    public function destroyLogoDiocese(): Response
    {
        $this->authorizeManage();
        $this->branding->destroyLogoDiocese();

        return response()->noContent();
    }

    /**
     * Serve a logo via URL assinada (sem Bearer / X-Tenant header).
     */
    public function showLogo(): StreamedResponse|Response
    {
        return $this->streamLogoPath(AppSetting::query()->first()?->logo_path);
    }

    public function showLogoDiocese(): StreamedResponse|Response
    {
        return $this->streamLogoPath(AppSetting::query()->first()?->logo_diocese_path);
    }

    private function streamLogoPath(?string $path): StreamedResponse|Response
    {
        if ($path === null || $path === '' || ! Storage::disk('public')->exists($path)) {
            abort(404);
        }

        return Storage::disk('public')->response($path);
    }

    private function authorizeManage(): void
    {
        $user = auth()->user();

        if ($user instanceof SuperAdmin) {
            return;
        }

        if (! $user instanceof User) {
            abort(403);
        }

        $previous = getPermissionsTeamId();
        setPermissionsTeamId(null);
        $ok = $user->hasRole('admin-tenant');
        setPermissionsTeamId($previous);

        abort_unless($ok, 403);
    }
}
