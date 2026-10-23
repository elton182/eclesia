<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Casal;
use App\Models\EccEquipe;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class EccEquipeService
{
    public function __construct(
        private readonly IgrejaContext $igrejaContext,
        private readonly EccVisibilityScope $visibility,
        private readonly UserService $users,
    ) {}

    /**
     * @return Collection<int, EccEquipe>
     */
    public function list(): Collection
    {
        $query = EccEquipe::query()
            ->where('igreja_id', $this->igrejaContext->current()->id)
            ->with('acessoUser:id,username')
            ->withCount('casais');

        $equipeIds = $this->visibility->restrictedEquipeIds();
        if ($equipeIds !== null) {
            $query->whereIn('id', $equipeIds);
        }

        return $query->orderBy('nome')->get();
    }

    public function find(string $id): EccEquipe
    {
        $query = EccEquipe::query()
            ->where('igreja_id', $this->igrejaContext->current()->id)
            ->with('acessoUser:id,username')
            ->withCount('casais');

        $equipeIds = $this->visibility->restrictedEquipeIds();
        if ($equipeIds !== null) {
            $query->whereIn('id', $equipeIds);
        }

        return $query->findOrFail($id);
    }

    /**
     * @param  array{nome: string, cor?: string|null}  $data
     */
    public function create(array $data): EccEquipe
    {
        return EccEquipe::query()->create([
            'igreja_id' => $this->igrejaContext->current()->id,
            'nome' => Str::upper(trim($data['nome'])),
            'cor' => $data['cor'] ?? null,
        ]);
    }

    /**
     * @param  array{nome?: string, cor?: string|null}  $data
     */
    public function update(EccEquipe $equipe, array $data): EccEquipe
    {
        if (isset($data['nome'])) {
            $data['nome'] = Str::upper(trim($data['nome']));
        }

        $equipe->update($data);

        return $equipe->refresh()->load(['acessoUser:id,username'])->loadCount('casais');
    }

    public function delete(EccEquipe $equipe): void
    {
        $equipe->delete();
    }

    public function findOrCreateByNome(string $nome): EccEquipe
    {
        $nome = Str::upper(trim($nome));
        $igrejaId = $this->igrejaContext->current()->id;

        return EccEquipe::query()->firstOrCreate(
            ['igreja_id' => $igrejaId, 'nome' => $nome],
            ['cor' => null],
        );
    }

    /**
     * @param  list<string>  $casalIds
     */
    public function syncCasais(EccEquipe $equipe, array $casalIds): EccEquipe
    {
        $igrejaId = $this->igrejaContext->current()->id;
        $casalIds = array_values(array_unique(array_map('strval', $casalIds)));

        if ($casalIds !== []) {
            $found = Casal::query()
                ->where('igreja_id', $igrejaId)
                ->whereIn('id', $casalIds)
                ->pluck('id')
                ->map(fn ($id) => (string) $id)
                ->all();

            $missing = array_diff($casalIds, $found);
            if ($missing !== []) {
                throw ValidationException::withMessages([
                    'casal_ids' => ['Um ou mais casais não foram encontrados nesta igreja.'],
                ]);
            }
        }

        DB::transaction(function () use ($equipe, $igrejaId, $casalIds): void {
            Casal::query()
                ->where('igreja_id', $igrejaId)
                ->where('ecc_equipe_id', $equipe->id)
                ->whereNotIn('id', $casalIds === [] ? ['_'] : $casalIds)
                ->update(['ecc_equipe_id' => null]);

            if ($casalIds !== []) {
                Casal::query()
                    ->where('igreja_id', $igrejaId)
                    ->whereIn('id', $casalIds)
                    ->update(['ecc_equipe_id' => $equipe->id]);
            }
        });

        return $equipe->refresh()->load(['acessoUser:id,username'])->loadCount('casais');
    }

    /**
     * @return array{username: string, password: string, equipe: EccEquipe}
     */
    public function criarAcesso(EccEquipe $equipe): array
    {
        if ($equipe->acesso_user_id !== null) {
            throw ValidationException::withMessages([
                'acesso' => ['Esta equipe já possui acesso. Use o reset de senha.'],
            ]);
        }

        $igreja = $this->igrejaContext->current();
        $username = $this->uniqueUsernameFromNome($equipe->nome);
        $password = $this->generateTemporaryPassword();
        $email = sprintf('%s.%s@equipe.local', $username, Str::lower(Str::substr((string) $igreja->id, 0, 8)));

        $user = $this->users->create([
            'name' => $equipe->nome,
            'email' => $email,
            'password' => $password,
            'is_active' => true,
        ]);

        $user->username = $username;
        $user->must_change_password = true;
        $user->save();

        $this->users->syncRoles(
            $user,
            (string) $igreja->id,
            [['name' => 'lider-equipe', 'equipe_ids' => [$equipe->id]]],
            actorIsSuperAdmin: true,
        );

        $equipe->acesso_user_id = $user->id;
        $equipe->save();

        return [
            'username' => $username,
            'password' => $password,
            'equipe' => $equipe->refresh()->load(['acessoUser:id,username'])->loadCount('casais'),
        ];
    }

    /**
     * @return array{username: string, password: string, equipe: EccEquipe}
     */
    public function resetSenhaAcesso(EccEquipe $equipe): array
    {
        if ($equipe->acesso_user_id === null) {
            throw ValidationException::withMessages([
                'acesso' => ['Esta equipe ainda não possui acesso. Crie o acesso primeiro.'],
            ]);
        }

        $user = User::query()->findOrFail($equipe->acesso_user_id);
        $password = $this->generateTemporaryPassword();

        $user->password = $password;
        $user->must_change_password = true;
        $user->save();
        $user->tokens()->delete();

        return [
            'username' => (string) $user->username,
            'password' => $password,
            'equipe' => $equipe->refresh()->load(['acessoUser:id,username'])->loadCount('casais'),
        ];
    }

    /**
     * SPEC-024: altera o username do acesso compartilhado (sem resetar senha).
     *
     * @return array{username: string, equipe: EccEquipe}
     */
    public function alterarLoginAcesso(EccEquipe $equipe, string $username): array
    {
        if ($equipe->acesso_user_id === null) {
            throw ValidationException::withMessages([
                'acesso' => ['Esta equipe ainda não possui acesso. Crie o acesso primeiro.'],
            ]);
        }

        $username = $this->normalizeUsername($username);
        if ($username === '') {
            throw ValidationException::withMessages([
                'username' => ['Informe um login válido (letras, números e hífens).'],
            ]);
        }

        $user = User::query()->findOrFail($equipe->acesso_user_id);

        if (User::usernameExists($username, $user->id)) {
            throw ValidationException::withMessages([
                'username' => ['Este login já está em uso.'],
            ]);
        }

        $igreja = $this->igrejaContext->current();
        $email = sprintf('%s.%s@equipe.local', $username, Str::lower(Str::substr((string) $igreja->id, 0, 8)));

        $user->username = $username;
        $user->email = $email;
        $user->save();
        $user->tokens()->delete();

        return [
            'username' => $username,
            'equipe' => $equipe->refresh()->load(['acessoUser:id,username'])->loadCount('casais'),
        ];
    }

    private function uniqueUsernameFromNome(string $nome): string
    {
        $base = $this->normalizeUsername($nome);
        if ($base === '') {
            $base = 'equipe';
        }

        $candidate = $base;
        $suffix = 2;
        while (User::usernameExists($candidate)) {
            $candidate = $base.'-'.$suffix;
            $suffix++;
        }

        return $candidate;
    }

    private function normalizeUsername(string $value): string
    {
        return Str::slug(Str::lower(trim($value)), '-');
    }

    /**
     * Senha inicial fixa para facilitar o repasse ao líder (SPEC-021).
     * Continua exigindo must_change_password no primeiro acesso.
     */
    private function generateTemporaryPassword(): string
    {
        return '1234';
    }

    /**
     * SPEC-025: PDF com equipe, piloto(s) e login do acesso.
     */
    public function pdf(): Response
    {
        $igreja = $this->igrejaContext->current();

        $equipes = EccEquipe::query()
            ->where('igreja_id', $igreja->id)
            ->with([
                'acessoUser:id,username',
                'casais' => static fn ($q) => $q->where('piloto', true)->with(['pessoaA:id,nome', 'pessoaB:id,nome']),
            ])
            ->orderBy('nome')
            ->get();

        $linhas = $equipes->map(function (EccEquipe $equipe): array {
            $pilotos = $equipe->casais
                ->map(fn (Casal $casal) => $this->formatCasalNome($casal))
                ->filter(fn (string $nome) => $nome !== '')
                ->values()
                ->all();

            return [
                'equipe' => $equipe->nome,
                'piloto' => $pilotos === [] ? '—' : implode('; ', $pilotos),
                'login' => $equipe->acessoUser?->username ?: '—',
            ];
        })->all();

        $pdf = Pdf::loadView('ecc.equipes-pdf', [
            'igreja' => $igreja,
            'linhas' => $linhas,
            'geradoEm' => now()->timezone(config('app.timezone', 'UTC'))->format('d/m/Y H:i'),
        ])->setPaper('a4', 'portrait');

        $slug = Str::slug((string) ($igreja->slug ?? $igreja->nome ?? 'igreja'), '-') ?: 'igreja';

        return $pdf->download('equipes-'.$slug.'.pdf');
    }

    private function formatCasalNome(Casal $casal): string
    {
        $a = trim((string) ($casal->pessoaA?->nome ?? ''));
        $b = trim((string) ($casal->pessoaB?->nome ?? ''));

        if ($a !== '' && $b !== '') {
            return $a.' & '.$b;
        }

        return $a !== '' ? $a : $b;
    }
}
