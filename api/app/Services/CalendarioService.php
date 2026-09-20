<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\CalendarioColetaLink;
use App\Models\CalendarioEventoTipo;
use App\Models\CalendarioIndisponibilidade;
use App\Models\CalendarioItem;
use App\Models\CalendarioLocal;
use App\Models\CalendarioMensal;
use App\Models\CalendarioObservacao;
use App\Models\CalendarioSlotPadrao;
use App\Models\CalendarioTempoLiturgico;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class CalendarioService
{
    public function __construct(private readonly IgrejaContext $igrejaContext) {}

    public function listLocais(): Collection
    {
        return CalendarioLocal::query()
            ->where('igreja_id', $this->igrejaContext->current()->id)
            ->orderBy('ordem')
            ->orderBy('nome')
            ->get();
    }

    /**
     * @param  array{nome: string, ordem?: int, ativo?: bool}  $data
     */
    public function createLocal(array $data): CalendarioLocal
    {
        return CalendarioLocal::query()->create([
            'igreja_id' => $this->igrejaContext->current()->id,
            'nome' => $data['nome'],
            'ordem' => $data['ordem'] ?? 0,
            'ativo' => $data['ativo'] ?? true,
        ]);
    }

    public function findLocal(string $id): CalendarioLocal
    {
        return CalendarioLocal::query()
            ->where('igreja_id', $this->igrejaContext->current()->id)
            ->whereKey($id)
            ->firstOrFail();
    }

    /**
     * @param  array{nome?: string, ordem?: int, ativo?: bool}  $data
     */
    public function updateLocal(CalendarioLocal $local, array $data): CalendarioLocal
    {
        $local->fill($data);
        $local->save();

        return $local->refresh();
    }

    public function deleteLocal(CalendarioLocal $local): void
    {
        $local->delete();
    }

    public function listSlots(): Collection
    {
        return CalendarioSlotPadrao::query()
            ->with('local')
            ->where('igreja_id', $this->igrejaContext->current()->id)
            ->orderBy('dia_semana')
            ->orderBy('hora')
            ->get();
    }

    /**
     * @param  array{local_id: string, dia_semana: int, hora: string, secao?: string, ordem?: int, ativo?: bool}  $data
     */
    public function createSlot(array $data): CalendarioSlotPadrao
    {
        $this->findLocal($data['local_id']);

        return CalendarioSlotPadrao::query()->create([
            'igreja_id' => $this->igrejaContext->current()->id,
            'local_id' => $data['local_id'],
            'dia_semana' => $data['dia_semana'],
            'hora' => $data['hora'],
            'secao' => $data['secao'] ?? 'semana',
            'ordem' => $data['ordem'] ?? 0,
            'ativo' => $data['ativo'] ?? true,
        ])->load('local');
    }

    public function findSlot(string $id): CalendarioSlotPadrao
    {
        return CalendarioSlotPadrao::query()
            ->with('local')
            ->where('igreja_id', $this->igrejaContext->current()->id)
            ->whereKey($id)
            ->firstOrFail();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateSlot(CalendarioSlotPadrao $slot, array $data): CalendarioSlotPadrao
    {
        if (isset($data['local_id'])) {
            $this->findLocal($data['local_id']);
        }
        $slot->fill($data);
        $slot->save();

        return $slot->refresh()->load('local');
    }

    public function deleteSlot(CalendarioSlotPadrao $slot): void
    {
        $slot->delete();
    }

    public function listMensais(): Collection
    {
        return CalendarioMensal::query()
            ->where('igreja_id', $this->igrejaContext->current()->id)
            ->orderByDesc('ano')
            ->orderByDesc('mes')
            ->get();
    }

    /**
     * @param  array{ano: int, mes: int, titulo?: ?string, subtitulo?: ?string, copiar_anterior?: bool}  $data
     */
    public function createMensal(array $data): CalendarioMensal
    {
        $igrejaId = $this->igrejaContext->current()->id;

        $exists = CalendarioMensal::query()
            ->where('igreja_id', $igrejaId)
            ->where('ano', $data['ano'])
            ->where('mes', $data['mes'])
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'mes' => ['Já existe calendário para este mês.'],
            ]);
        }

        return DB::transaction(function () use ($data, $igrejaId) {
            $mensal = CalendarioMensal::query()->create([
                'igreja_id' => $igrejaId,
                'ano' => $data['ano'],
                'mes' => $data['mes'],
                'status' => CalendarioMensal::STATUS_RASCUNHO,
                'titulo' => $data['titulo'] ?? null,
                'subtitulo' => $data['subtitulo'] ?? null,
            ]);

            if (! empty($data['copiar_anterior'])) {
                $this->copiarDoAnterior($mensal);
            } else {
                $this->gerarItensDosSlots($mensal);
            }

            return $this->findMensal($mensal->id);
        });
    }

    public function findMensal(string $id): CalendarioMensal
    {
        $mensal = CalendarioMensal::query()
            ->with([
                'itens.local',
                'itens.tipo',
                'observacoes',
                'temposLiturgicos',
                'indisponibilidades',
                'coletaLinks',
            ])
            ->where('igreja_id', $this->igrejaContext->current()->id)
            ->whereKey($id)
            ->firstOrFail();

        $this->gerarTemposLiturgicos($mensal);
        $mensal->load('temposLiturgicos');

        return $mensal;
    }

    /**
     * @param  array{titulo?: ?string, subtitulo?: ?string}  $data
     */
    public function updateMensal(CalendarioMensal $mensal, array $data): CalendarioMensal
    {
        $this->assertEditavel($mensal);
        $mensal->fill($data);
        $mensal->save();

        return $this->findMensal($mensal->id);
    }

    public function deleteMensal(CalendarioMensal $mensal): void
    {
        $mensal->delete();
    }

    /**
     * Copia o calendário para o mês seguinte (rascunho).
     */
    public function copiarParaProximoMes(CalendarioMensal $origem): CalendarioMensal
    {
        $proxMes = $origem->mes === 12 ? 1 : $origem->mes + 1;
        $proxAno = $origem->mes === 12 ? $origem->ano + 1 : $origem->ano;

        $exists = CalendarioMensal::query()
            ->where('igreja_id', $origem->igreja_id)
            ->where('ano', $proxAno)
            ->where('mes', $proxMes)
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'mes' => ['Já existe calendário para o próximo mês.'],
            ]);
        }

        return DB::transaction(function () use ($origem, $proxAno, $proxMes) {
            $origem->load(['itens', 'observacoes']);

            $destino = CalendarioMensal::query()->create([
                'igreja_id' => $origem->igreja_id,
                'ano' => $proxAno,
                'mes' => $proxMes,
                'status' => CalendarioMensal::STATUS_RASCUNHO,
                'titulo' => $origem->titulo,
                'subtitulo' => $origem->subtitulo,
            ]);

            $obsMap = [];
            foreach ($origem->observacoes as $obs) {
                $nova = CalendarioObservacao::query()->create([
                    'calendario_mensal_id' => $destino->id,
                    'titulo' => $obs->titulo,
                    'descricao' => $obs->descricao,
                    'ordem' => $obs->ordem,
                ]);
                $obsMap[$obs->id] = $nova->id;
            }

            $datasOrigemPorDow = $this->indexarDatasPorDow($origem->ano, $origem->mes);
            $datasDestinoPorDow = $this->indexarDatasPorDow($destino->ano, $destino->mes);

            foreach ($origem->itens as $item) {
                $novaData = $this->mapearDataCopia(
                    $item,
                    $origem,
                    $destino,
                    $datasOrigemPorDow,
                    $datasDestinoPorDow,
                );
                if ($novaData === null) {
                    continue;
                }

                CalendarioItem::query()->create([
                    'calendario_mensal_id' => $destino->id,
                    'local_id' => $item->local_id,
                    'tipo_id' => $item->tipo_id,
                    'data' => $novaData,
                    'hora' => $item->hora,
                    'secao' => $item->secao,
                    'titulo' => $item->titulo,
                    'pessoa_id' => $item->pessoa_id,
                    'celebrante_nome' => $item->celebrante_nome,
                    'notas' => $item->notas,
                    'observacao_id' => $item->observacao_id
                        ? ($obsMap[$item->observacao_id] ?? null)
                        : null,
                    'ordem' => $item->ordem,
                ]);
            }

            // Se a origem não tinha grade (só slots), gera a partir dos slots padrão
            if ($destino->itens()->whereIn('secao', ['fds', 'semana'])->doesntExist()) {
                $this->gerarItensDosSlots($destino);
            }

            return $this->findMensal($destino->id);
        });
    }

    /**
     * @return array<int, list<string>> dayOfWeek => datas Y-m-d ordenadas
     */
    private function indexarDatasPorDow(int $ano, int $mes): array
    {
        $map = [];
        for ($dow = 0; $dow <= 6; $dow++) {
            $map[$dow] = $this->pdfDatasDoMes($ano, $mes, $dow);
        }

        return $map;
    }

    /**
     * @param  array<int, list<string>>  $datasOrigemPorDow
     * @param  array<int, list<string>>  $datasDestinoPorDow
     */
    private function mapearDataCopia(
        CalendarioItem $item,
        CalendarioMensal $origem,
        CalendarioMensal $destino,
        array $datasOrigemPorDow,
        array $datasDestinoPorDow,
    ): ?string {
        $dataIso = $item->data->format('Y-m-d');
        $dow = (int) $item->data->dayOfWeek;

        if (in_array($item->secao, ['fds', 'semana'], true)) {
            $listaOrigem = $datasOrigemPorDow[$dow] ?? [];
            $idx = array_search($dataIso, $listaOrigem, true);
            if ($idx === false) {
                return null;
            }
            $listaDestino = $datasDestinoPorDow[$dow] ?? [];

            return $listaDestino[$idx] ?? null;
        }

        // festa / casamento / obs_movel: mesmo dia do mês, se existir
        $dia = (int) $item->data->day;
        if (! checkdate($destino->mes, $dia, $destino->ano)) {
            return null;
        }

        return sprintf('%04d-%02d-%02d', $destino->ano, $destino->mes, $dia);
    }

    public function transitionStatus(CalendarioMensal $mensal, string $status): CalendarioMensal
    {
        $allowed = match ($mensal->status) {
            CalendarioMensal::STATUS_RASCUNHO => [CalendarioMensal::STATUS_COLETA, CalendarioMensal::STATUS_MONTAGEM],
            CalendarioMensal::STATUS_COLETA => [CalendarioMensal::STATUS_MONTAGEM, CalendarioMensal::STATUS_RASCUNHO],
            CalendarioMensal::STATUS_MONTAGEM => [CalendarioMensal::STATUS_FECHADO, CalendarioMensal::STATUS_COLETA],
            CalendarioMensal::STATUS_FECHADO => [CalendarioMensal::STATUS_MONTAGEM],
            default => [],
        };

        if (! in_array($status, $allowed, true)) {
            throw ValidationException::withMessages([
                'status' => ["Transição de {$mensal->status} para {$status} não permitida."],
            ]);
        }

        $mensal->status = $status;
        if ($status === CalendarioMensal::STATUS_FECHADO) {
            $mensal->fechado_em = now();
        } elseif ($mensal->fechado_em !== null) {
            $mensal->fechado_em = null;
        }
        $mensal->save();

        return $this->findMensal($mensal->id);
    }

    /**
     * @param  array{data: string, tipo_id?: string, secao?: string, local_id?: ?string, hora?: ?string, titulo?: ?string, pessoa_id?: ?string, celebrante_nome?: ?string, notas?: ?string, observacao_id?: ?string, ordem?: int}  $data
     */
    public function createItem(CalendarioMensal $mensal, array $data): CalendarioItem
    {
        $this->assertEditavel($mensal);
        $this->assertDataNoMes($mensal, $data['data']);

        if (! empty($data['local_id'])) {
            $this->findLocal($data['local_id']);
        }

        $observacaoId = $this->resolveObservacaoId($mensal, $data['observacao_id'] ?? null);

        $tipo = null;
        $secao = $data['secao'] ?? null;
        $titulo = $data['titulo'] ?? null;

        if (! empty($data['tipo_id'])) {
            $tipo = CalendarioEventoTipo::query()
                ->whereKey($data['tipo_id'])
                ->where('ativo', true)
                ->firstOrFail();

            $secao = $this->resolverSecaoDoTipo($tipo, $data['data']);

            if ($tipo->exige_titulo) {
                $custom = trim((string) ($data['titulo'] ?? ''));
                if ($custom === '') {
                    throw ValidationException::withMessages([
                        'titulo' => ['Informe o nome do evento para o tipo Outro.'],
                    ]);
                }
                $titulo = $custom;
            } else {
                $titulo = $titulo ?: $tipo->nome;
            }
        }

        if ($secao === null || $secao === '') {
            throw ValidationException::withMessages([
                'tipo_id' => ['Informe o tipo do evento.'],
            ]);
        }

        if (! in_array($secao, CalendarioItem::SECOES, true)) {
            throw ValidationException::withMessages([
                'secao' => ['Seção inválida.'],
            ]);
        }

        return CalendarioItem::query()->create([
            'calendario_mensal_id' => $mensal->id,
            'local_id' => $data['local_id'] ?? null,
            'tipo_id' => $tipo?->id,
            'data' => $data['data'],
            'hora' => $data['hora'] ?? null,
            'secao' => $secao,
            'titulo' => $titulo,
            'pessoa_id' => $data['pessoa_id'] ?? null,
            'celebrante_nome' => $data['celebrante_nome'] ?? null,
            'notas' => $data['notas'] ?? null,
            'observacao_id' => $observacaoId,
            'ordem' => $data['ordem'] ?? 0,
        ])->load(['local', 'tipo']);
    }

    public function listEventoTipos(bool $somenteAtivos = true): Collection
    {
        CalendarioEventoTipo::seedDefaults();

        $q = CalendarioEventoTipo::query()->orderBy('ordem')->orderBy('nome');
        if ($somenteAtivos) {
            $q->where('ativo', true);
        }

        return $q->get();
    }

    public function findEventoTipo(string $id): CalendarioEventoTipo
    {
        return CalendarioEventoTipo::query()->whereKey($id)->firstOrFail();
    }

    /**
     * @param  array{nome: string, secao_padrao: string, exige_titulo?: bool, ordem?: int, ativo?: bool}  $data
     */
    public function createEventoTipo(array $data): CalendarioEventoTipo
    {
        $slug = $this->slugifyTipoNome($data['nome']);
        if (CalendarioEventoTipo::query()->where('slug', $slug)->exists()) {
            $slug = $slug.'-'.Str::lower(Str::random(4));
        }

        return CalendarioEventoTipo::query()->create([
            'slug' => $slug,
            'nome' => $data['nome'],
            'secao_padrao' => $data['secao_padrao'],
            'exige_titulo' => $data['exige_titulo'] ?? false,
            'ordem' => $data['ordem'] ?? ((int) CalendarioEventoTipo::query()->max('ordem') + 1),
            'ativo' => $data['ativo'] ?? true,
            'sistema' => false,
        ]);
    }

    /**
     * @param  array{nome?: string, secao_padrao?: string, exige_titulo?: bool, ordem?: int, ativo?: bool}  $data
     */
    public function updateEventoTipo(CalendarioEventoTipo $tipo, array $data): CalendarioEventoTipo
    {
        if ($tipo->sistema && array_key_exists('exige_titulo', $data) && $tipo->slug === CalendarioEventoTipo::SLUG_OUTRO) {
            $data['exige_titulo'] = true;
        }

        $tipo->fill(collect($data)->only([
            'nome', 'secao_padrao', 'exige_titulo', 'ordem', 'ativo',
        ])->all());
        $tipo->save();

        return $tipo->refresh();
    }

    public function deleteEventoTipo(CalendarioEventoTipo $tipo): void
    {
        if ($tipo->sistema) {
            throw ValidationException::withMessages([
                'tipo' => ['Tipos do sistema não podem ser excluídos. Desative-os se necessário.'],
            ]);
        }

        if ($tipo->itens()->exists()) {
            throw ValidationException::withMessages([
                'tipo' => ['Tipo em uso em eventos do calendário. Desative em vez de excluir.'],
            ]);
        }

        $tipo->delete();
    }

    private function slugifyTipoNome(string $nome): string
    {
        $slug = Str::slug($nome, '-');

        return $slug !== '' ? $slug : 'tipo-'.Str::lower(Str::random(6));
    }

    private function resolverSecaoDoTipo(CalendarioEventoTipo $tipo, string $dataIso): string
    {
        return match ($tipo->secao_padrao) {
            CalendarioEventoTipo::SECAO_CASAMENTO => 'casamento',
            CalendarioEventoTipo::SECAO_FESTA => 'festa',
            CalendarioEventoTipo::SECAO_GRADE => $this->secaoGradePorData($dataIso),
            default => 'festa',
        };
    }

    private function secaoGradePorData(string $dataIso): string
    {
        $dow = (int) \Carbon\Carbon::parse($dataIso)->dayOfWeek;

        return ($dow === 0 || $dow === 6) ? 'fds' : 'semana';
    }

    public function findItem(string $id): CalendarioItem
    {
        $item = CalendarioItem::query()->with(['local', 'calendario'])->whereKey($id)->firstOrFail();
        if ($item->calendario->igreja_id !== $this->igrejaContext->current()->id) {
            throw (new NotFoundHttpException);
        }

        return $item;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateItem(CalendarioItem $item, array $data): CalendarioItem
    {
        $this->assertEditavel($item->calendario);
        if (isset($data['data'])) {
            $this->assertDataNoMes($item->calendario, $data['data']);
        }
        if (! empty($data['local_id'])) {
            $this->findLocal($data['local_id']);
        }
        if (array_key_exists('observacao_id', $data)) {
            $data['observacao_id'] = $this->resolveObservacaoId($item->calendario, $data['observacao_id']);
        }
        $item->fill($data);
        $item->save();

        return $item->refresh()->load(['local', 'tipo']);
    }

    public function deleteItem(CalendarioItem $item): void
    {
        $this->assertEditavel($item->calendario);
        $item->delete();
    }

    /**
     * @param  array{titulo: string, descricao: string, ordem?: int}  $data
     */
    public function createObservacao(CalendarioMensal $mensal, array $data): CalendarioObservacao
    {
        $this->assertEditavel($mensal);

        $ordem = $data['ordem'] ?? ((int) $mensal->observacoes()->max('ordem') + 1);

        return CalendarioObservacao::query()->create([
            'calendario_mensal_id' => $mensal->id,
            'titulo' => $data['titulo'],
            'descricao' => $data['descricao'],
            'ordem' => $ordem,
        ]);
    }

    public function findObservacao(string $id): CalendarioObservacao
    {
        $obs = CalendarioObservacao::query()->with('calendario')->whereKey($id)->firstOrFail();
        $this->assertMensalDaIgreja($obs->calendario_mensal_id);

        return $obs;
    }

    /**
     * @param  array{titulo?: string, descricao?: string, ordem?: int}  $data
     */
    public function updateObservacao(CalendarioObservacao $obs, array $data): CalendarioObservacao
    {
        $this->assertEditavel($obs->calendario);
        $obs->fill(collect($data)->only(['titulo', 'descricao', 'ordem'])->all());
        $obs->save();

        return $obs->refresh();
    }

    public function deleteObservacao(CalendarioObservacao $obs): void
    {
        $this->assertEditavel($obs->calendario);
        $obs->delete();
    }

    public function findTempoLiturgico(string $id): CalendarioTempoLiturgico
    {
        $tempo = CalendarioTempoLiturgico::query()->with('calendario')->whereKey($id)->firstOrFail();
        $this->assertMensalDaIgreja($tempo->calendario_mensal_id);

        return $tempo;
    }

    /**
     * @param  array{rotulo: string}  $data
     */
    public function updateTempoLiturgico(CalendarioTempoLiturgico $tempo, array $data): CalendarioTempoLiturgico
    {
        $this->assertEditavel($tempo->calendario);
        $tempo->rotulo = isset($data['rotulo']) ? (trim((string) $data['rotulo']) ?: null) : $tempo->rotulo;
        $tempo->save();

        return $tempo->refresh();
    }

    /**
     * @param  array{rotulo?: ?string, expira_em?: ?string}  $data
     * @return array{link: CalendarioColetaLink, token: string}
     */
    public function createColetaLink(CalendarioMensal $mensal, array $data = []): array
    {
        if (! in_array($mensal->status, [CalendarioMensal::STATUS_COLETA, CalendarioMensal::STATUS_RASCUNHO, CalendarioMensal::STATUS_MONTAGEM], true)) {
            throw ValidationException::withMessages([
                'status' => ['Coleta só é permitida antes do fechamento.'],
            ]);
        }

        if ($mensal->status === CalendarioMensal::STATUS_RASCUNHO) {
            $mensal->status = CalendarioMensal::STATUS_COLETA;
            $mensal->save();
        }

        $existing = $mensal->coletaLinks()
            ->where('ativo', true)
            ->whereNotNull('token')
            ->where(function ($q) {
                $q->whereNull('expira_em')->orWhere('expira_em', '>', now());
            })
            ->latest('id')
            ->first();

        if ($existing !== null) {
            if (! empty($data['rotulo']) && $existing->rotulo !== $data['rotulo']) {
                $existing->rotulo = $data['rotulo'];
                $existing->save();
            }

            return ['link' => $existing, 'token' => (string) $existing->token];
        }

        $plain = Str::random(48);
        $link = CalendarioColetaLink::query()->create([
            'calendario_mensal_id' => $mensal->id,
            'token' => $plain,
            'token_hash' => hash('sha256', $plain),
            'token_preview' => substr($plain, 0, 8),
            'rotulo' => $data['rotulo'] ?? null,
            'expira_em' => $data['expira_em'] ?? now()->addDays(21),
            'ativo' => true,
        ]);

        return ['link' => $link, 'token' => $plain];
    }

    public function findColetaLinkByToken(string $token): CalendarioColetaLink
    {
        $hash = hash('sha256', $token);
        $link = CalendarioColetaLink::query()
            ->with('calendario')
            ->where('ativo', true)
            ->where(function ($q) use ($token, $hash) {
                $q->where('token', $token)->orWhere('token_hash', $hash);
            })
            ->first();


        if ($link === null) {
            throw new NotFoundHttpException('Link de coleta inválido.');
        }

        if ($link->expira_em !== null && $link->expira_em->isPast()) {
            throw ValidationException::withMessages([
                'token' => ['Link de coleta expirado.'],
            ]);
        }

        if ($link->calendario->isFechado()) {
            throw ValidationException::withMessages([
                'token' => ['Calendário já fechado.'],
            ]);
        }

        return $link;
    }

    /**
     * @param  array{nome_exibicao: string, datas: list<string>, motivo?: ?string}  $data
     * @return Collection<int, CalendarioIndisponibilidade>
     */
    public function registrarIndisponibilidadesPublicas(CalendarioColetaLink $link, array $data): Collection
    {
        $mensal = $link->calendario;
        $created = collect();

        foreach ($data['datas'] as $dataStr) {
            $this->assertDataNoMes($mensal, $dataStr);
            $created->push(CalendarioIndisponibilidade::query()->create([
                'calendario_mensal_id' => $mensal->id,
                'coleta_link_id' => $link->id,
                'nome_exibicao' => $data['nome_exibicao'],
                'data' => $dataStr,
                'motivo' => $data['motivo'] ?? null,
            ]));
        }

        return $created;
    }

    /**
     * @param  array{nome_exibicao: string, datas: list<string>, motivo?: ?string, pessoa_id?: ?string}  $data
     * @return Collection<int, CalendarioIndisponibilidade>
     */
    public function registrarIndisponibilidadesAuth(CalendarioMensal $mensal, array $data, ?int $userId = null): Collection
    {
        if ($mensal->isFechado()) {
            throw ValidationException::withMessages([
                'status' => ['Calendário fechado.'],
            ]);
        }

        $created = collect();
        foreach ($data['datas'] as $dataStr) {
            $this->assertDataNoMes($mensal, $dataStr);
            $created->push(CalendarioIndisponibilidade::query()->create([
                'calendario_mensal_id' => $mensal->id,
                'user_id' => $userId,
                'pessoa_id' => $data['pessoa_id'] ?? null,
                'nome_exibicao' => $data['nome_exibicao'],
                'data' => $dataStr,
                'motivo' => $data['motivo'] ?? null,
            ]));
        }

        return $created;
    }

    public function pdf(CalendarioMensal $mensal): Response
    {
        $mensal->load(['itens.local', 'itens.tipo', 'observacoes', 'temposLiturgicos', 'igreja']);
        $meses = [
            1 => 'JANEIRO', 2 => 'FEVEREIRO', 3 => 'MARÇO', 4 => 'ABRIL',
            5 => 'MAIO', 6 => 'JUNHO', 7 => 'JULHO', 8 => 'AGOSTO',
            9 => 'SETEMBRO', 10 => 'OUTUBRO', 11 => 'NOVEMBRO', 12 => 'DEZEMBRO',
        ];

        $branding = $this->pdfBrandingContext($mensal->igreja);
        $corMap = $this->pdfCelebranteCores($mensal->itens);
        $obsNumeroMap = $this->pdfObservacaoNumeroMap($mensal->observacoes);

        $pdf = Pdf::loadView('calendario.oficial-pdf', [
            'mensal' => $mensal,
            'mesNome' => $meses[$mensal->mes] ?? (string) $mensal->mes,
            'gradeSemana' => $this->pdfGradeSemana($mensal->itens, $corMap, $mensal->ano, $mensal->mes, $obsNumeroMap),
            'gradeFds' => $this->pdfGradeFds($mensal->itens, $corMap, $mensal->ano, $mensal->mes, $obsNumeroMap),
            'temposLiturgicosLabels' => $this->pdfTemposLiturgicosLabels($mensal),
            'observacoes' => $mensal->observacoes->values(),
            'festas' => $mensal->itens->where('secao', 'festa')->sortBy(
                fn (CalendarioItem $i) => $i->data->format('Y-m-d').'-'.sprintf('%05d', $this->pdfSortKeyHora($i->hora)).'-'.$i->ordem
            )->values(),
            'casamentos' => $mensal->itens->where('secao', 'casamento')->sortBy(
                fn (CalendarioItem $i) => $i->data->format('Y-m-d').'-'.sprintf('%05d', $this->pdfSortKeyHora($i->hora))
            )->values(),
            'obsMoveis' => $mensal->itens->where('secao', 'obs_movel')->sortBy(
                fn (CalendarioItem $i) => $i->data->format('Y-m-d').'-'.sprintf('%05d', $this->pdfSortKeyHora($i->hora))
            )->values(),
            'corMap' => $corMap,
            ...$branding,
        ])->setPaper('a4', 'portrait');

        $filename = sprintf('calendario-%04d-%02d.pdf', $mensal->ano, $mensal->mes);

        return $pdf->download($filename);
    }

    /**
     * @param  Collection<int, CalendarioItem>  $itens
     * @return array<string, string> nome normalizado => cor hex
     */
    private function pdfCelebranteCores(Collection $itens): array
    {
        $palette = [
            '#1E5AA8', // azul
            '#3D6B2F', // verde
            '#C45C1A', // laranja
            '#8B1E3D', // vinho
            '#5C4A1A', // oliva
            '#2F6B6B', // teal
        ];

        $nomes = $itens
            ->pluck('celebrante_nome')
            ->filter(fn ($n) => is_string($n) && trim($n) !== '' && mb_strtolower(trim($n)) !== 'não haverá')
            ->map(fn ($n) => trim((string) $n))
            ->unique()
            ->sort()
            ->values();

        $map = [];
        foreach ($nomes as $i => $nome) {
            $map[mb_strtolower($nome)] = $palette[$i % count($palette)];
        }

        return $map;
    }

    /**
     * @param  Collection<int, CalendarioItem>  $itens
     * @param  array<string, string>  $corMap
     * @param  array<string, int>  $obsNumeroMap
     * @return list<array{diaLabel: string, datas: list<string>, linhas: list<array{local: string, hora: string, celulas: list<array{texto: string, cor: ?string, ref: ?string, cancelado: bool}>}>}>
     */
    private function pdfGradeSemana(
        Collection $itens,
        array $corMap,
        int $ano,
        int $mes,
        array $obsNumeroMap = [],
    ): array {
        $labels = [
            1 => 'SEGUNDA',
            2 => 'TERÇA',
            3 => 'QUARTA',
            4 => 'QUINTA',
            5 => 'SEXTA',
        ];

        $semana = $itens->where('secao', 'semana')->values();

        $grupos = [];
        foreach ([1, 2, 3, 4, 5] as $dow) {
            $datasIso = $this->pdfDatasDoMes($ano, $mes, $dow);
            $doDia = $semana->filter(fn (CalendarioItem $i) => (int) $i->data->dayOfWeek === $dow);
            $grupos[] = $this->pdfMontarBlocoDia($labels[$dow], $doDia, $corMap, $datasIso, $obsNumeroMap);
        }

        return $grupos;
    }

    /**
     * @param  Collection<int, CalendarioItem>  $itens
     * @param  array<string, string>  $corMap
     * @param  array<string, int>  $obsNumeroMap
     * @return list<array{diaLabel: string, datas: list<string>, linhas: list<array{local: string, hora: string, celulas: list<array{texto: string, cor: ?string, ref: ?string, cancelado: bool}>}>}>
     */
    private function pdfGradeFds(
        Collection $itens,
        array $corMap,
        int $ano,
        int $mes,
        array $obsNumeroMap = [],
    ): array {
        $fds = $itens->where('secao', 'fds')->values();
        if ($fds->isEmpty()) {
            return [];
        }

        $grupos = [];
        foreach ([6 => 'SÁBADO', 0 => 'DOMINGO'] as $dow => $label) {
            $datasIso = $this->pdfDatasDoMes($ano, $mes, $dow);
            $doDia = $fds->filter(fn (CalendarioItem $i) => (int) $i->data->dayOfWeek === $dow);
            if ($doDia->isEmpty()) {
                continue;
            }
            $grupos[] = $this->pdfMontarBlocoDia($label, $doDia, $corMap, $datasIso, $obsNumeroMap);
        }

        return $grupos;
    }

    /**
     * @return list<string> datas Y-m-d
     */
    private function pdfDatasDoMes(int $ano, int $mes, int $dayOfWeek): array
    {
        $datas = [];
        $cursor = \Carbon\Carbon::create($ano, $mes, 1)->startOfDay();
        $end = $cursor->copy()->endOfMonth();

        while ($cursor->lte($end)) {
            if ((int) $cursor->dayOfWeek === $dayOfWeek) {
                $datas[] = $cursor->format('Y-m-d');
            }
            $cursor->addDay();
        }

        return $datas;
    }

    /**
     * @param  Collection<int, CalendarioItem>  $itensDoDia
     * @param  array<string, string>  $corMap
     * @param  list<string>|null  $datasIsoForcadas
     * @param  array<string, int>  $obsNumeroMap
     * @return array{diaLabel: string, datas: list<string>, linhas: list<array{local: string, hora: string, celulas: list<array{texto: string, cor: ?string, ref: ?string, cancelado: bool}>}>}
     */
    private function pdfMontarBlocoDia(
        string $diaLabel,
        Collection $itensDoDia,
        array $corMap,
        ?array $datasIsoForcadas = null,
        array $obsNumeroMap = [],
    ): array {
        $datas = $datasIsoForcadas !== null
            ? collect($datasIsoForcadas)->values()
            : $itensDoDia
                ->map(fn (CalendarioItem $i) => $i->data->format('Y-m-d'))
                ->unique()
                ->sort()
                ->values();

        $datasLabel = $datas->map(fn (string $d) => substr($d, 8, 2))->all();

        $linhasKeys = $itensDoDia
            ->sortBy(fn (CalendarioItem $i) => sprintf(
                '%05d-%05d-%s',
                $this->pdfSortKeyHora($i->hora),
                $i->local?->ordem ?? 999,
                mb_strtolower((string) ($i->local?->nome ?? '')),
            ))
            ->map(fn (CalendarioItem $i) => [
                'key' => ($i->local_id ?? 'sem').'|'.($i->hora ?? ''),
                'local' => mb_strtoupper((string) ($i->local?->nome ?? '—')),
                'hora' => $this->pdfFormatHora($i->hora),
            ])
            ->unique('key')
            ->values();

        $index = [];
        foreach ($itensDoDia as $item) {
            $key = ($item->local_id ?? 'sem').'|'.($item->hora ?? '').'|'.$item->data->format('Y-m-d');
            $index[$key] = $item;
        }

        $linhas = [];
        foreach ($linhasKeys as $linha) {
            $celulas = [];
            foreach ($datas as $dataIso) {
                $item = $index[$linha['key'].'|'.$dataIso] ?? null;
                $celulas[] = $this->pdfCelulaCelebrante($item, $corMap, $obsNumeroMap);
            }
            $linhas[] = [
                'local' => $linha['local'],
                'hora' => $linha['hora'],
                'celulas' => $celulas,
            ];
        }

        return [
            'diaLabel' => $diaLabel,
            'datas' => $datasLabel,
            'linhas' => $linhas,
        ];
    }

    /**
     * @param  array<string, string>  $corMap
     * @param  array<string, int>  $obsNumeroMap
     * @return array{texto: string, cor: ?string, ref: ?string, cancelado: bool}
     */
    private function pdfCelulaCelebrante(?CalendarioItem $item, array $corMap, array $obsNumeroMap = []): array
    {
        if ($item === null) {
            return ['texto' => '-', 'cor' => null, 'ref' => null, 'cancelado' => false];
        }

        $nome = trim((string) ($item->celebrante_nome ?? ''));
        $notas = trim((string) ($item->notas ?? ''));
        $notasLower = mb_strtolower($notas);
        $nomeLower = mb_strtolower($nome);

        if ($nomeLower === 'não haverá' || $nomeLower === 'nao havera') {
            return ['texto' => 'Não haverá', 'cor' => null, 'ref' => null, 'cancelado' => true];
        }

        if ($nome === '' && (
            str_contains($notasLower, 'não haverá')
            || str_contains($notasLower, 'nao havera')
            || str_contains($notasLower, 'cancelad')
        )) {
            return ['texto' => 'Não haverá', 'cor' => null, 'ref' => null, 'cancelado' => true];
        }

        if ($nome === '') {
            return ['texto' => '-', 'cor' => null, 'ref' => null, 'cancelado' => false];
        }

        $ref = null;
        if ($item->observacao_id && isset($obsNumeroMap[$item->observacao_id])) {
            $ref = (string) $obsNumeroMap[$item->observacao_id];
        } elseif (preg_match('/\((\d+)\)/', $notas, $m)) {
            $ref = $m[1];
        }

        return [
            'texto' => $nome,
            'cor' => $corMap[$nomeLower] ?? null,
            'ref' => $ref,
            'cancelado' => false,
        ];
    }

    /** Formato oficial do PDF: 07H, 19H30. */
    public static function formatHoraPdf(?string $hora): string
    {
        if ($hora === null || $hora === '') {
            return '—';
        }

        $h = substr($hora, 0, 5);
        if (! preg_match('/^(\d{2}):(\d{2})$/', $h, $m)) {
            return $hora;
        }

        return $m[2] === '00' ? $m[1].'H' : $m[1].'H'.$m[2];
    }

    private function pdfFormatHora(?string $hora): string
    {
        return self::formatHoraPdf($hora);
    }

    /** Minutos desde meia-noite para ordenação estável (sem hora → fim). */
    private function pdfSortKeyHora(?string $hora): int
    {
        if ($hora === null || trim($hora) === '') {
            return 24 * 60;
        }

        if (preg_match('/^(\d{1,2}):(\d{2})/', $hora, $m)) {
            return ((int) $m[1]) * 60 + (int) $m[2];
        }

        return 24 * 60;
    }

    /**
     * @return array{
     *   logoDioceseSrc: ?string,
     *   logoParoquiaSrc: ?string,
     *   localidadeLinha: string,
     *   corPrimary: string,
     *   corSecondary: string,
     *   corTexto: string,
     *   corMuted: string,
     *   corOnPrimary: string
     * }
     */
    private function pdfBrandingContext(?\App\Models\Igreja $igreja): array
    {
        $settings = \App\Models\AppSetting::query()->first();
        $cores = is_array($settings?->cores) ? $settings->cores : [];

        $primary = $cores['primary'] ?? '#4E1220';
        $secondary = $cores['secondary'] ?? ($cores['accent'] ?? '#C88A5E');
        $texto = $cores['text'] ?? '#2A1418';
        $muted = $cores['text_muted'] ?? '#6B4A50';
        $onPrimary = $cores['on_primary'] ?? '#FFFFFF';

        $logoParoquiaSrc = $this->pdfLogoDataUri($settings?->logo_path);
        $logoDioceseSrc = $this->pdfLogoDataUri($settings?->logo_diocese_path);

        $cidade = trim((string) ($igreja?->cidade ?? ''));
        $uf = strtoupper(trim((string) ($igreja?->uf ?? '')));
        $diocese = trim((string) ($igreja?->diocese ?? ''));

        // Formato oficial: "Jundiaí / Diocese de Jundiaí – SP"
        $localidadeLinha = '';
        if ($cidade !== '' && $diocese !== '' && $uf !== '') {
            $localidadeLinha = $cidade.' / '.$diocese.' – '.$uf;
        } elseif ($cidade !== '' && $diocese !== '') {
            $localidadeLinha = $cidade.' / '.$diocese;
        } elseif ($cidade !== '' && $uf !== '') {
            $localidadeLinha = $cidade.' – '.$uf;
        } elseif ($diocese !== '' && $uf !== '') {
            $localidadeLinha = $diocese.' – '.$uf;
        } elseif ($cidade !== '') {
            $localidadeLinha = $cidade;
        } elseif ($diocese !== '') {
            $localidadeLinha = $diocese;
        } elseif ($uf !== '') {
            $localidadeLinha = $uf;
        }

        return [
            'logoDioceseSrc' => $logoDioceseSrc,
            'logoParoquiaSrc' => $logoParoquiaSrc,
            'localidadeLinha' => $localidadeLinha,
            'corPrimary' => $primary,
            'corSecondary' => $secondary,
            'corTexto' => $texto,
            'corMuted' => $muted,
            'corOnPrimary' => $onPrimary,
        ];
    }

    private function pdfLogoDataUri(?string $path): ?string
    {
        if (! is_string($path) || $path === '') {
            return null;
        }

        if (! \Illuminate\Support\Facades\Storage::disk('public')->exists($path)) {
            return null;
        }

        $full = \Illuminate\Support\Facades\Storage::disk('public')->path($path);
        $mime = @mime_content_type($full) ?: 'image/png';
        $bin = @file_get_contents($full);
        if ($bin === false) {
            return null;
        }

        return 'data:'.$mime.';base64,'.base64_encode($bin);
    }

    private function copiarDoAnterior(CalendarioMensal $mensal): void
    {
        $prevMes = $mensal->mes === 1 ? 12 : $mensal->mes - 1;
        $prevAno = $mensal->mes === 1 ? $mensal->ano - 1 : $mensal->ano;

        $anterior = CalendarioMensal::query()
            ->where('igreja_id', $mensal->igreja_id)
            ->where('ano', $prevAno)
            ->where('mes', $prevMes)
            ->with('observacoes')
            ->first();

        if ($anterior === null) {
            $this->gerarItensDosSlots($mensal);

            return;
        }

        foreach ($anterior->observacoes as $obs) {
            CalendarioObservacao::query()->create([
                'calendario_mensal_id' => $mensal->id,
                'titulo' => $obs->titulo,
                'descricao' => $obs->descricao,
                'ordem' => $obs->ordem,
            ]);
        }

        $this->gerarItensDosSlots($mensal);
    }

    private function gerarTemposLiturgicos(CalendarioMensal $mensal): void
    {
        foreach ($this->pdfDatasDoMes($mensal->ano, $mensal->mes, 0) as $dataDomingo) {
            $exists = CalendarioTempoLiturgico::query()
                ->where('calendario_mensal_id', $mensal->id)
                ->whereDate('data_domingo', $dataDomingo)
                ->exists();

            if ($exists) {
                continue;
            }

            CalendarioTempoLiturgico::query()->create([
                'calendario_mensal_id' => $mensal->id,
                'data_domingo' => $dataDomingo,
                'rotulo' => null,
            ]);
        }
    }

    /**
     * @param  Collection<int, CalendarioObservacao>  $observacoes
     * @return array<string, int> observacao_id => numero 1-based
     */
    private function pdfObservacaoNumeroMap(Collection $observacoes): array
    {
        $map = [];
        foreach ($observacoes->values() as $i => $obs) {
            $map[$obs->id] = $i + 1;
        }

        return $map;
    }

    /**
     * @return list<string>
     */
    private function pdfTemposLiturgicosLabels(CalendarioMensal $mensal): array
    {
        $domingos = $this->pdfDatasDoMes($mensal->ano, $mensal->mes, 0);
        $byDate = $mensal->temposLiturgicos->keyBy(
            fn (CalendarioTempoLiturgico $t) => $t->data_domingo->format('Y-m-d')
        );

        return array_map(
            fn (string $d) => trim((string) ($byDate->get($d)?->rotulo ?? '')),
            $domingos,
        );
    }

    private function resolveObservacaoId(CalendarioMensal $mensal, mixed $observacaoId): ?string
    {
        if ($observacaoId === null || $observacaoId === '') {
            return null;
        }

        $obs = CalendarioObservacao::query()
            ->where('calendario_mensal_id', $mensal->id)
            ->whereKey((string) $observacaoId)
            ->first();

        if ($obs === null) {
            throw ValidationException::withMessages([
                'observacao_id' => ['Observação inválida para este calendário.'],
            ]);
        }

        return $obs->id;
    }

    private function assertMensalDaIgreja(string $mensalId): void
    {
        $exists = CalendarioMensal::query()
            ->where('igreja_id', $this->igrejaContext->current()->id)
            ->whereKey($mensalId)
            ->exists();

        if (! $exists) {
            throw new NotFoundHttpException;
        }
    }

    private function gerarItensDosSlots(CalendarioMensal $mensal): void
    {
        $slots = CalendarioSlotPadrao::query()
            ->where('igreja_id', $mensal->igreja_id)
            ->where('ativo', true)
            ->get();

        if ($slots->isEmpty()) {
            return;
        }

        $start = \Carbon\Carbon::create($mensal->ano, $mensal->mes, 1)->startOfDay();
        $end = $start->copy()->endOfMonth();

        for ($day = $start->copy(); $day->lte($end); $day->addDay()) {
            $dow = (int) $day->dayOfWeek; // 0 domingo
            foreach ($slots->where('dia_semana', $dow) as $slot) {
                CalendarioItem::query()->create([
                    'calendario_mensal_id' => $mensal->id,
                    'local_id' => $slot->local_id,
                    'data' => $day->toDateString(),
                    'hora' => $slot->hora,
                    'secao' => in_array($dow, [0, 6], true) ? 'fds' : ($slot->secao ?: 'semana'),
                    'ordem' => $slot->ordem,
                ]);
            }
        }
    }

    private function assertEditavel(CalendarioMensal $mensal): void
    {
        if ($mensal->isFechado()) {
            throw ValidationException::withMessages([
                'status' => ['Calendário fechado não pode ser editado.'],
            ]);
        }
    }

    private function assertDataNoMes(CalendarioMensal $mensal, string $data): void
    {
        $carbon = \Carbon\Carbon::parse($data);
        if ((int) $carbon->year !== $mensal->ano || (int) $carbon->month !== $mensal->mes) {
            throw ValidationException::withMessages([
                'data' => ['Data deve pertencer ao mês do calendário.'],
            ]);
        }
    }
}
