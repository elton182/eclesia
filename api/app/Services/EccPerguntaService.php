<?php

declare(strict_types=1);

namespace App\Services;

use App\Events\EccPerguntaEnvioCriado;
use App\Models\Casal;
use App\Models\EccEquipe;
use App\Models\EccPergunta;
use App\Models\EccPerguntaCampo;
use App\Models\EccPerguntaEnvio;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EccPerguntaService
{
    public function __construct(
        private readonly IgrejaContext $igrejaContext,
        private readonly EccVisibilityScope $visibility,
    ) {}

    /**
     * @param  array{status?: string}  $filters
     * @return list<array<string, mixed>>
     */
    public function list(array $filters = []): array
    {
        $this->assertCanView();

        $query = EccPergunta::query()
            ->with(['campos', 'responsavel'])
            ->withCount('envios')
            ->where('igreja_id', $this->igrejaId())
            ->orderByDesc('disponibilizado_em')
            ->orderByDesc('created_at');

        $canManage = $this->visibility->userCan('ecc.perguntas.manage');
        $user = auth()->user() instanceof User ? auth()->user() : null;

        if ($canManage || ($user && $this->isResponsavelDeAlguma($user))) {
            if (! empty($filters['status'])) {
                $query->where('status', $filters['status']);
            }
        } else {
            $query->where('status', EccPergunta::STATUS_DISPONIVEL);
        }

        $equipeIds = $this->visibility->restrictedEquipeIds($user) ?? [];

        return $query->get()
            ->map(fn (EccPergunta $p): array => $this->serialize($p, $user, $equipeIds, listMode: true))
            ->values()
            ->all();
    }

    /**
     * Resumo para o cartão na Minha equipe.
     *
     * @return array{pendentes: int, com_resposta_nova: int}
     */
    public function resumoLider(): array
    {
        $this->assertCanView();

        $user = auth()->user();
        if (! $user instanceof User) {
            return ['pendentes' => 0, 'com_resposta_nova' => 0];
        }

        $equipeIds = $this->visibility->restrictedEquipeIds($user) ?? [];
        if ($equipeIds === [] && ! $this->visibility->userCan('ecc.perguntas.manage')) {
            // líder sem equipes
            if ($this->visibility->restrictedEquipeIds($user) !== null) {
                return ['pendentes' => 0, 'com_resposta_nova' => 0];
            }
        }

        $disponiveis = EccPergunta::query()
            ->where('igreja_id', $this->igrejaId())
            ->where('status', EccPergunta::STATUS_DISPONIVEL)
            ->with(['envios' => function ($q) use ($equipeIds): void {
                if ($equipeIds !== []) {
                    $q->whereIn('equipe_id', $equipeIds);
                }
            }])
            ->get();

        $pendentes = 0;
        $comResposta = 0;

        foreach ($disponiveis as $pergunta) {
            if ($equipeIds === []) {
                continue;
            }
            foreach ($equipeIds as $equipeId) {
                $envio = $pergunta->envios->first(
                    fn (EccPerguntaEnvio $e): bool => (string) $e->equipe_id === (string) $equipeId,
                );
                if ($envio === null) {
                    $pendentes++;
                } elseif ($envio->status === EccPerguntaEnvio::STATUS_RESPONDIDO) {
                    $comResposta++;
                }
            }
        }

        return [
            'pendentes' => $pendentes,
            'com_resposta_nova' => $comResposta,
        ];
    }

    public function find(string $id): EccPergunta
    {
        $this->assertCanView();

        $pergunta = EccPergunta::query()
            ->with(['campos', 'responsavel', 'envios.equipe'])
            ->withCount('envios')
            ->where('igreja_id', $this->igrejaId())
            ->whereKey($id)
            ->firstOrFail();

        $user = auth()->user() instanceof User ? auth()->user() : null;
        $canManage = $this->visibility->userCan('ecc.perguntas.manage');
        $isResponsavel = $user && (int) $pergunta->responsavel_user_id === (int) $user->id;

        if (
            ! $canManage
            && ! $isResponsavel
            && $pergunta->status !== EccPergunta::STATUS_DISPONIVEL
        ) {
            abort(404);
        }

        return $pergunta;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, User $actor): EccPergunta
    {
        $this->assertCanManage();

        return DB::transaction(function () use ($data, $actor): EccPergunta {
            $payload = $this->normalizeFormulario($data);
            $campos = $this->normalizeCampos($data['campos'] ?? []);

            $pergunta = EccPergunta::query()->create([
                ...$payload,
                'igreja_id' => $this->igrejaId(),
                'criado_por_user_id' => $actor->id,
            ]);

            $this->syncCampos($pergunta, $campos);

            return $pergunta->fresh(['campos', 'responsavel']) ?? $pergunta;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(EccPergunta $pergunta, array $data): EccPergunta
    {
        $this->assertCanManage();
        $this->assertSameIgreja($pergunta);

        if ($pergunta->status === EccPergunta::STATUS_ARQUIVADO) {
            throw ValidationException::withMessages([
                'status' => ['Formulário arquivado não pode ser editado.'],
            ]);
        }

        return DB::transaction(function () use ($pergunta, $data): EccPergunta {
            $payload = $this->normalizeFormulario($data, $pergunta);
            $pergunta->update($payload);

            if (array_key_exists('campos', $data)) {
                if ($pergunta->envios()->exists()) {
                    throw ValidationException::withMessages([
                        'campos' => ['Não é possível alterar campos após o primeiro envio.'],
                    ]);
                }
                $this->syncCampos($pergunta, $this->normalizeCampos($data['campos'] ?? []));
            }

            return $pergunta->fresh(['campos', 'responsavel']) ?? $pergunta;
        });
    }

    public function disponibilizar(EccPergunta $pergunta): EccPergunta
    {
        $this->assertCanManage();
        $this->assertSameIgreja($pergunta);

        if ($pergunta->campos()->count() < 1) {
            throw ValidationException::withMessages([
                'campos' => ['Inclua ao menos um campo antes de disponibilizar.'],
            ]);
        }

        $pergunta->update([
            'status' => EccPergunta::STATUS_DISPONIVEL,
            'disponibilizado_em' => now(),
        ]);

        return $pergunta->fresh(['campos', 'responsavel']) ?? $pergunta;
    }

    public function arquivar(EccPergunta $pergunta): EccPergunta
    {
        $this->assertCanManage();
        $this->assertSameIgreja($pergunta);

        $pergunta->update(['status' => EccPergunta::STATUS_ARQUIVADO]);

        return $pergunta->fresh(['campos', 'responsavel']) ?? $pergunta;
    }

    public function delete(EccPergunta $pergunta): void
    {
        $this->assertCanManage();
        $this->assertSameIgreja($pergunta);

        if ($pergunta->envios()->exists()) {
            throw ValidationException::withMessages([
                'id' => ['Não é possível excluir: já existem envios. Arquive o formulário.'],
            ]);
        }

        $pergunta->delete();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function enviar(EccPergunta $pergunta, array $data, User $actor): EccPerguntaEnvio
    {
        $this->assertCanView();
        $this->assertSameIgreja($pergunta);

        if ($pergunta->status !== EccPergunta::STATUS_DISPONIVEL) {
            throw ValidationException::withMessages([
                'pergunta_id' => ['Este formulário não está disponível.'],
            ]);
        }

        $equipeId = (string) ($data['equipe_id'] ?? '');
        $this->assertEquipePermitida($equipeId, $actor);

        if (
            EccPerguntaEnvio::query()
                ->where('pergunta_id', $pergunta->id)
                ->where('equipe_id', $equipeId)
                ->exists()
        ) {
            throw ValidationException::withMessages([
                'equipe_id' => ['Esta equipe já respondeu a este formulário.'],
            ]);
        }

        $respostas = $this->normalizeRespostas($pergunta, $data['respostas'] ?? []);

        $envio = EccPerguntaEnvio::query()->create([
            'pergunta_id' => $pergunta->id,
            'equipe_id' => $equipeId,
            'enviado_por_user_id' => $actor->id,
            'respostas' => $respostas,
            'status' => EccPerguntaEnvio::STATUS_ENVIADO,
            'enviado_em' => now(),
        ]);

        $responsavel = User::query()->find($pergunta->responsavel_user_id);
        if ($responsavel) {
            event(new EccPerguntaEnvioCriado($envio, (string) $responsavel->ulid));
        }

        return $envio->fresh(['equipe', 'pergunta']) ?? $envio;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listEnvios(EccPergunta $pergunta): array
    {
        $this->assertSameIgreja($pergunta);
        $user = auth()->user();
        abort_unless($user instanceof User, 403);

        $canManage = $this->visibility->userCan('ecc.perguntas.manage');
        $isResponsavel = (int) $pergunta->responsavel_user_id === (int) $user->id;
        abort_unless($canManage || $isResponsavel, 403);

        return $pergunta->envios()
            ->with([
                'equipe.casais' => static fn ($q) => $q->where('piloto', true)->with(['pessoaA:id,nome', 'pessoaB:id,nome']),
                'enviadoPor',
                'respondidoPor',
            ])
            ->orderByDesc('enviado_em')
            ->get()
            ->map(fn (EccPerguntaEnvio $e): array => $this->serializeEnvio($e))
            ->values()
            ->all();
    }

    /**
     * @param  array{resposta: string}  $data
     */
    public function responder(EccPerguntaEnvio $envio, array $data, User $actor): EccPerguntaEnvio
    {
        $pergunta = $envio->pergunta ?? EccPergunta::query()->findOrFail($envio->pergunta_id);
        $this->assertSameIgreja($pergunta);

        $canManage = $this->visibility->userCan('ecc.perguntas.manage');
        $isResponsavel = (int) $pergunta->responsavel_user_id === (int) $actor->id;
        abort_unless($canManage || $isResponsavel, 403);

        $texto = trim((string) ($data['resposta'] ?? ''));
        if ($texto === '') {
            throw ValidationException::withMessages([
                'resposta' => ['Informe a resposta.'],
            ]);
        }

        $envio->update([
            'resposta_responsavel' => $texto,
            'status' => EccPerguntaEnvio::STATUS_RESPONDIDO,
            'respondido_em' => now(),
            'respondido_por_user_id' => $actor->id,
        ]);

        return $envio->fresh(['equipe', 'pergunta', 'respondidoPor']) ?? $envio;
    }

    /**
     * @param  list<string>  $equipeIds
     * @return array<string, mixed>
     */
    public function serialize(
        EccPergunta $pergunta,
        ?User $user = null,
        array $equipeIds = [],
        bool $listMode = false,
    ): array {
        $pergunta->loadMissing(['campos', 'responsavel']);

        $meuEnvio = null;
        if ($user && $equipeIds !== []) {
            $envio = EccPerguntaEnvio::query()
                ->where('pergunta_id', $pergunta->id)
                ->whereIn('equipe_id', $equipeIds)
                ->with([
                    'equipe.casais' => static fn ($q) => $q->where('piloto', true)->with(['pessoaA:id,nome', 'pessoaB:id,nome']),
                ])
                ->orderByDesc('enviado_em')
                ->first();
            if ($envio) {
                $meuEnvio = $this->serializeEnvio($envio);
            }
        }

        $base = [
            'id' => (string) $pergunta->id,
            'titulo' => (string) $pergunta->titulo,
            'descricao' => $pergunta->descricao,
            'mensagem_pos_envio' => $pergunta->mensagem_pos_envio,
            'rotulo_resposta' => $this->rotuloRespostaEfetivo($pergunta->rotulo_resposta),
            'status' => (string) $pergunta->status,
            'responsavel_user_id' => (string) ($pergunta->responsavel?->ulid ?? ''),
            'responsavel_nome' => (string) ($pergunta->responsavel?->name ?? ''),
            'disponibilizado_em' => $pergunta->disponibilizado_em?->toIso8601String(),
            'campos_count' => $pergunta->campos->count(),
            'tem_envios' => ((int) ($pergunta->envios_count ?? $pergunta->envios()->count())) > 0,
            'meu_envio' => $meuEnvio,
            'envios_count' => $this->visibility->userCan('ecc.perguntas.manage')
                || ($user && (int) $pergunta->responsavel_user_id === (int) $user->id)
                ? (int) ($pergunta->envios_count ?? $pergunta->envios()->count())
                : null,
        ];

        if ($listMode) {
            return $base;
        }

        $base['campos'] = $pergunta->campos->map(fn (EccPerguntaCampo $c): array => [
            'id' => (string) $c->id,
            'ordem' => (int) $c->ordem,
            'tipo' => (string) $c->tipo,
            'rotulo' => (string) $c->rotulo,
            'obrigatorio' => (bool) $c->obrigatorio,
            'opcoes' => $c->opcoes,
        ])->values()->all();

        return $base;
    }

    /**
     * @return array<string, mixed>
     */
    public function serializeEnvio(EccPerguntaEnvio $envio): array
    {
        $envio->loadMissing([
            'equipe.casais' => static fn ($q) => $q->where('piloto', true)->with(['pessoaA:id,nome', 'pessoaB:id,nome']),
            'enviadoPor',
            'respondidoPor',
        ]);

        return [
            'id' => (string) $envio->id,
            'pergunta_id' => (string) $envio->pergunta_id,
            'equipe_id' => (string) $envio->equipe_id,
            'equipe_nome' => (string) ($envio->equipe?->nome ?? ''),
            'casal_piloto' => $this->formatCasalPiloto($envio->equipe),
            'enviado_por_user_id' => (string) ($envio->enviadoPor?->ulid ?? ''),
            'enviado_por_nome' => (string) ($envio->enviadoPor?->name ?? ''),
            'respostas' => $envio->respostas ?? [],
            'status' => (string) $envio->status,
            'enviado_em' => $envio->enviado_em?->toIso8601String(),
            'resposta_responsavel' => $envio->resposta_responsavel,
            'respondido_em' => $envio->respondido_em?->toIso8601String(),
            'respondido_por_nome' => (string) ($envio->respondidoPor?->name ?? ''),
        ];
    }

    public function rotuloRespostaEfetivo(?string $rotulo): string
    {
        $limpo = trim((string) $rotulo);

        return $limpo !== '' ? $limpo : 'Resposta à equipe';
    }

    private function formatCasalPiloto(?EccEquipe $equipe): string
    {
        if (! $equipe) {
            return '';
        }

        $pilotos = $equipe->casais
            ->map(function (Casal $casal): string {
                $a = trim((string) ($casal->pessoaA?->nome ?? ''));
                $b = trim((string) ($casal->pessoaB?->nome ?? ''));
                if ($a !== '' && $b !== '') {
                    return $a.' & '.$b;
                }

                return $a !== '' ? $a : $b;
            })
            ->filter(static fn (string $nome): bool => $nome !== '')
            ->values()
            ->all();

        return $pilotos === [] ? '' : implode('; ', $pilotos);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalizeFormulario(array $data, ?EccPergunta $existing = null): array
    {
        $responsavelId = $existing?->responsavel_user_id;
        if (array_key_exists('responsavel_user_id', $data)) {
            $responsavelUlid = (string) $data['responsavel_user_id'];
            $responsavel = User::query()->where('ulid', $responsavelUlid)->first();
            if (! $responsavel) {
                throw ValidationException::withMessages([
                    'responsavel_user_id' => ['Usuário responsável inválido.'],
                ]);
            }
            $responsavelId = $responsavel->id;
        }

        if (! $responsavelId) {
            throw ValidationException::withMessages([
                'responsavel_user_id' => ['Informe o usuário responsável.'],
            ]);
        }

        $descricao = $existing?->descricao;
        if (array_key_exists('descricao', $data)) {
            $descricao = ($data['descricao'] !== null && $data['descricao'] !== '')
                ? (string) $data['descricao']
                : null;
        }

        $mensagemPos = $existing?->mensagem_pos_envio;
        if (array_key_exists('mensagem_pos_envio', $data)) {
            $mensagemPos = ($data['mensagem_pos_envio'] !== null && trim((string) $data['mensagem_pos_envio']) !== '')
                ? trim((string) $data['mensagem_pos_envio'])
                : null;
        }

        $rotuloResposta = $existing?->rotulo_resposta;
        if (array_key_exists('rotulo_resposta', $data)) {
            $rotuloResposta = ($data['rotulo_resposta'] !== null && trim((string) $data['rotulo_resposta']) !== '')
                ? trim((string) $data['rotulo_resposta'])
                : null;
        }

        return [
            'titulo' => (string) ($data['titulo'] ?? $existing?->titulo),
            'descricao' => $descricao,
            'mensagem_pos_envio' => $mensagemPos,
            'rotulo_resposta' => $rotuloResposta,
            'status' => $existing?->status ?? EccPergunta::STATUS_RASCUNHO,
            'responsavel_user_id' => $responsavelId,
        ];
    }

    /**
     * @param  mixed  $campos
     * @return list<array{ordem: int, tipo: string, rotulo: string, obrigatorio: bool, opcoes: list<string>|null}>
     */
    private function normalizeCampos(mixed $campos): array
    {
        if (! is_array($campos) || $campos === []) {
            throw ValidationException::withMessages([
                'campos' => ['Informe ao menos um campo.'],
            ]);
        }

        $out = [];
        foreach (array_values($campos) as $i => $campo) {
            if (! is_array($campo)) {
                throw ValidationException::withMessages([
                    "campos.{$i}" => ['Campo inválido.'],
                ]);
            }
            $tipo = (string) ($campo['tipo'] ?? '');
            if (! in_array($tipo, [
                EccPerguntaCampo::TIPO_TEXTO_CURTO,
                EccPerguntaCampo::TIPO_TEXTO_LONGO,
                EccPerguntaCampo::TIPO_LISTA,
            ], true)) {
                throw ValidationException::withMessages([
                    "campos.{$i}.tipo" => ['Tipo deve ser texto_curto, texto_longo ou lista.'],
                ]);
            }
            $rotulo = trim((string) ($campo['rotulo'] ?? ''));
            if ($rotulo === '') {
                throw ValidationException::withMessages([
                    "campos.{$i}.rotulo" => ['Informe o rótulo.'],
                ]);
            }
            $opcoes = null;
            if ($tipo === EccPerguntaCampo::TIPO_LISTA) {
                $raw = $campo['opcoes'] ?? [];
                if (! is_array($raw) || count($raw) < 1) {
                    throw ValidationException::withMessages([
                        "campos.{$i}.opcoes" => ['Lista precisa de ao menos uma opção.'],
                    ]);
                }
                $opcoes = array_values(array_map(
                    static fn (mixed $o): string => trim((string) $o),
                    $raw,
                ));
                $opcoes = array_values(array_filter($opcoes, static fn (string $o): bool => $o !== ''));
                if ($opcoes === []) {
                    throw ValidationException::withMessages([
                        "campos.{$i}.opcoes" => ['Lista precisa de ao menos uma opção.'],
                    ]);
                }
            }

            $out[] = [
                'ordem' => (int) ($campo['ordem'] ?? $i),
                'tipo' => $tipo,
                'rotulo' => $rotulo,
                'obrigatorio' => array_key_exists('obrigatorio', $campo)
                    ? filter_var($campo['obrigatorio'], FILTER_VALIDATE_BOOLEAN)
                    : true,
                'opcoes' => $opcoes,
            ];
        }

        return $out;
    }

    /**
     * @param  list<array{ordem: int, tipo: string, rotulo: string, obrigatorio: bool, opcoes: list<string>|null}>  $campos
     */
    private function syncCampos(EccPergunta $pergunta, array $campos): void
    {
        $pergunta->campos()->delete();
        foreach ($campos as $campo) {
            $pergunta->campos()->create($campo);
        }
    }

    /**
     * @param  mixed  $respostas
     * @return array<string, string>
     */
    private function normalizeRespostas(EccPergunta $pergunta, mixed $respostas): array
    {
        if (! is_array($respostas)) {
            throw ValidationException::withMessages([
                'respostas' => ['Informe as respostas.'],
            ]);
        }

        $pergunta->loadMissing('campos');
        $out = [];
        $errors = [];

        foreach ($pergunta->campos as $campo) {
            $key = (string) $campo->id;
            $valor = $respostas[$key] ?? $respostas[$campo->id] ?? null;
            $texto = is_string($valor) || is_numeric($valor) ? trim((string) $valor) : '';

            if ($campo->obrigatorio && $texto === '') {
                $errors["respostas.{$key}"] = ["Preencha: {$campo->rotulo}."];
                continue;
            }

            if ($texto === '') {
                continue;
            }

            if ($campo->tipo === EccPerguntaCampo::TIPO_LISTA) {
                $opcoes = $campo->opcoes ?? [];
                if (! in_array($texto, $opcoes, true)) {
                    $errors["respostas.{$key}"] = ['Opção inválida.'];
                    continue;
                }
            }

            if ($campo->tipo === EccPerguntaCampo::TIPO_TEXTO_CURTO && mb_strlen($texto) > 255) {
                $errors["respostas.{$key}"] = ['Texto curto no máximo 255 caracteres.'];
                continue;
            }

            if ($campo->tipo === EccPerguntaCampo::TIPO_TEXTO_LONGO && mb_strlen($texto) > 5000) {
                $errors["respostas.{$key}"] = ['Texto longo no máximo 5000 caracteres.'];
                continue;
            }

            $out[$key] = $texto;
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $out;
    }

    private function assertEquipePermitida(string $equipeId, User $actor): void
    {
        if ($equipeId === '') {
            throw ValidationException::withMessages([
                'equipe_id' => ['Informe a equipe.'],
            ]);
        }

        $exists = EccEquipe::query()
            ->where('igreja_id', $this->igrejaId())
            ->whereKey($equipeId)
            ->exists();

        if (! $exists) {
            throw ValidationException::withMessages([
                'equipe_id' => ['Equipe inválida para a igreja atual.'],
            ]);
        }

        if ($this->visibility->userCan('ecc.perguntas.manage', $actor)) {
            return;
        }

        $allowed = $this->visibility->restrictedEquipeIds($actor);
        if ($allowed !== null && ! in_array($equipeId, $allowed, true)) {
            throw ValidationException::withMessages([
                'equipe_id' => ['Você não pode responder por esta equipe.'],
            ]);
        }
    }

    private function isResponsavelDeAlguma(User $user): bool
    {
        return EccPergunta::query()
            ->where('igreja_id', $this->igrejaId())
            ->where('responsavel_user_id', $user->id)
            ->exists();
    }

    private function assertSameIgreja(EccPergunta $pergunta): void
    {
        if ((string) $pergunta->igreja_id !== $this->igrejaId()) {
            abort(404);
        }
    }

    private function assertCanView(): void
    {
        abort_unless(
            $this->visibility->userCan('ecc.perguntas.view')
                || $this->visibility->userCan('ecc.perguntas.manage'),
            403,
        );
    }

    private function assertCanManage(): void
    {
        abort_unless($this->visibility->userCan('ecc.perguntas.manage'), 403);
    }

    private function igrejaId(): string
    {
        return (string) $this->igrejaContext->current()->id;
    }
}
