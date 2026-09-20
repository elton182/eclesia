<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\EccEquipeServicoResource;
use App\Services\EccEquipeServicoService;
use App\Services\EccVisibilityScope;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class EccEquipeServicoController extends Controller
{
    public function __construct(
        private readonly EccEquipeServicoService $equipes,
        private readonly EccVisibilityScope $visibility,
    ) {}

    public function index(): AnonymousResourceCollection
    {
        abort_unless(
            $this->visibility->userCan('ecc.casais.view')
            || $this->visibility->userCan('ecc.casais.manage')
            || $this->visibility->userCan('ecc.equipes.view')
            || $this->visibility->userCan('ecc.equipes.manage'),
            403,
        );

        return EccEquipeServicoResource::collection($this->equipes->listAtivas());
    }
}
