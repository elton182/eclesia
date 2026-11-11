<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\PlanejamentoAnual;
use App\Models\PlanejamentoAnualToken;
use App\Models\PlanejamentoPastoralToken;

final class PlanejamentoPublicLink
{
    public const MODO_PASTORAL = 'pastoral';

    public const MODO_GENERICO = 'generico';

    public function __construct(
        public readonly string $modo,
        public readonly PlanejamentoAnual $anual,
        public readonly ?string $pastoralId = null,
        public readonly ?string $pastoralNome = null,
        public readonly ?PlanejamentoPastoralToken $tokenPastoral = null,
        public readonly ?PlanejamentoAnualToken $tokenAnual = null,
    ) {}

    public function isGenerico(): bool
    {
        return $this->modo === self::MODO_GENERICO;
    }

    public static function fromPastoral(PlanejamentoPastoralToken $token): self
    {
        $anual = $token->planejamento ?? PlanejamentoAnual::query()->findOrFail($token->planejamento_anual_id);

        return new self(
            self::MODO_PASTORAL,
            $anual,
            $token->pastoral_id,
            $token->pastoral?->nome,
            $token,
            null,
        );
    }

    public static function fromAnual(PlanejamentoAnualToken $token): self
    {
        $anual = $token->planejamento ?? PlanejamentoAnual::query()->findOrFail($token->planejamento_anual_id);

        return new self(
            self::MODO_GENERICO,
            $anual,
            null,
            null,
            null,
            $token,
        );
    }
}
