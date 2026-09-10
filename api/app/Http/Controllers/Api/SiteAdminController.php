<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSiteFormRequest;
use App\Http\Requests\StoreSiteMediaRequest;
use App\Http\Requests\StoreSitePageRequest;
use App\Http\Requests\UpdateSiteFormRequest;
use App\Http\Requests\UpdateSitePageRequest;
use App\Http\Requests\UpdateSiteSettingsRequest;
use App\Http\Resources\SiteFormResource;
use App\Http\Resources\SiteFormSubmissionResource;
use App\Http\Resources\SiteMediaResource;
use App\Http\Resources\SitePageResource;
use App\Http\Resources\SiteSettingResource;
use App\Models\SiteForm;
use App\Models\SitePage;
use App\Services\SiteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class SiteAdminController extends Controller
{
    public function __construct(
        private readonly SiteService $site,
    ) {}

    public function settings(): SiteSettingResource
    {
        $this->authorizePermission('site.settings.view');

        return new SiteSettingResource($this->site->settings());
    }

    public function updateSettings(UpdateSiteSettingsRequest $request): JsonResponse
    {
        $settings = $this->site->updateSettings($request->validated());
        $settings->wasRecentlyCreated = false;

        return (new SiteSettingResource($settings))->response();
    }

    public function pages(): AnonymousResourceCollection
    {
        $this->authorizePermission('site.pages.view');

        return SitePageResource::collection($this->site->listPages());
    }

    public function storePage(StoreSitePageRequest $request): JsonResponse
    {
        $page = $this->site->createPage($request->validated());

        return (new SitePageResource($page))->response()->setStatusCode(201);
    }

    public function showPage(SitePage $page): SitePageResource
    {
        $this->authorizePermission('site.pages.view');

        return new SitePageResource($page->load('blocks'));
    }

    public function updatePage(UpdateSitePageRequest $request, SitePage $page): SitePageResource
    {
        return new SitePageResource(
            $this->site->updatePage($page, $request->validated())
        );
    }

    public function destroyPage(SitePage $page): Response
    {
        $this->authorizePermission('site.pages.manage');
        $this->site->deletePage($page);

        return response()->noContent();
    }

    public function media(): AnonymousResourceCollection
    {
        $this->authorizePermission('site.pages.view');

        return SiteMediaResource::collection($this->site->listMedia());
    }

    public function storeMedia(StoreSiteMediaRequest $request): JsonResponse
    {
        $media = $this->site->storeMedia(
            $request->file('file'),
            $request->user(),
            $request->validated('alt'),
        );

        return (new SiteMediaResource($media))->response()->setStatusCode(201);
    }

    public function forms(): AnonymousResourceCollection
    {
        $this->authorizePermission('site.forms.view');

        return SiteFormResource::collection(
            SiteForm::query()->with('fields')->orderBy('nome')->get()
        );
    }

    public function storeForm(StoreSiteFormRequest $request): JsonResponse
    {
        $form = $this->site->createForm($request->validated());

        return (new SiteFormResource($form))->response()->setStatusCode(201);
    }

    public function showForm(SiteForm $form): SiteFormResource
    {
        $this->authorizePermission('site.forms.view');

        return new SiteFormResource($form->load('fields'));
    }

    public function updateForm(UpdateSiteFormRequest $request, SiteForm $form): SiteFormResource
    {
        return new SiteFormResource(
            $this->site->updateForm($form, $request->validated())
        );
    }

    public function destroyForm(SiteForm $form): Response
    {
        $this->authorizePermission('site.forms.manage');
        $form->delete();

        return response()->noContent();
    }

    public function submissions(SiteForm $form): AnonymousResourceCollection
    {
        $this->authorizePermission('site.forms.submissions.view');

        return SiteFormSubmissionResource::collection(
            $form->submissions()->orderByDesc('created_at')->get()
        );
    }

    private function authorizePermission(string $permission): void
    {
        abort_unless(auth()->user()?->can($permission), 403);
    }
}
