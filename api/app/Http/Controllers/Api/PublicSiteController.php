<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\IgrejaResource;
use App\Http\Resources\PastoralResource;
use App\Http\Resources\SiteComunicadoResource;
use App\Http\Resources\SiteFormSubmissionResource;
use App\Http\Resources\SitePageResource;
use App\Http\Resources\SiteSettingResource;
use App\Services\SiteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PublicSiteController extends Controller
{
    public function __construct(
        private readonly SiteService $site,
    ) {}

    public function show(): JsonResponse
    {
        $home = $this->site->publicHome();

        return response()->json([
            'data' => [
                'settings' => (new SiteSettingResource($home['settings']))->resolve(),
                'page' => $home['page'] !== null
                    ? (new SitePageResource($home['page']))->resolve()
                    : null,
            ],
        ]);
    }

    public function page(string $slug): SitePageResource
    {
        return new SitePageResource($this->site->publicPage($slug));
    }

    public function comunicados(): AnonymousResourceCollection
    {
        return SiteComunicadoResource::collection($this->site->publicComunicados());
    }

    public function comunicado(string $id): SiteComunicadoResource
    {
        return new SiteComunicadoResource($this->site->publicComunicado($id));
    }

    public function pastorais(Request $request): AnonymousResourceCollection
    {
        return PastoralResource::collection(
            $this->site->publicPastorais($request->query('igreja'))
        );
    }

    public function igrejas(): AnonymousResourceCollection
    {
        return IgrejaResource::collection($this->site->publicIgrejas());
    }

    public function igreja(string $slug): IgrejaResource
    {
        return new IgrejaResource($this->site->publicIgreja($slug));
    }

    public function form(string $slug): JsonResponse
    {
        $this->site->assertPublished();

        $form = \App\Models\SiteForm::query()
            ->with('fields')
            ->where('slug', $slug)
            ->where('ativo', true)
            ->first();

        if ($form === null) {
            throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException('Formulário não encontrado.');
        }

        return response()->json([
            'data' => [
                'id' => $form->id,
                'nome' => $form->nome,
                'slug' => $form->slug,
                'descricao' => $form->descricao,
                'sucesso_mensagem' => $form->sucesso_mensagem,
                'fields' => $form->fields->map(static fn ($f) => [
                    'id' => $f->id,
                    'nome' => $f->nome,
                    'label' => $f->label,
                    'tipo' => $f->tipo,
                    'obrigatorio' => (bool) $f->obrigatorio,
                    'opcoes' => $f->opcoes ?? [],
                    'ordem' => $f->ordem,
                ])->values(),
            ],
        ]);
    }

    public function submitForm(Request $request, string $slug): JsonResponse
    {
        $submission = $this->site->submitForm(
            $slug,
            is_array($request->input('values')) ? $request->input('values') : [],
            $request->input('website'),
            $request->ip(),
            $request->userAgent(),
        );

        return (new SiteFormSubmissionResource($submission))
            ->response()
            ->setStatusCode(201);
    }
}
