<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Services\EccVisibilityScope;

class UpdateEccCasalRequest extends StoreEccCasalRequest
{
    public function authorize(): bool
    {
        $scope = app(EccVisibilityScope::class);

        return $scope->userCan('ecc.casais.manage') || $scope->userCan('ecc.casais.atualizar');
    }
}
