<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\EccReflexao;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;

class EccReflexaoService
{
    private const MAX_IMAGE_KB = 5120;

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

        $query = EccReflexao::query()
            ->where('igreja_id', $this->igrejaId())
            ->orderByDesc('publicado_em')
            ->orderByDesc('created_at');

        if ($this->visibility->userCan('ecc.reflexoes.manage')) {
            if (! empty($filters['status'])) {
                $query->where('status', $filters['status']);
            }
        } else {
            $query->where('status', EccReflexao::STATUS_PUBLICADO);
        }

        return $query->get()
            ->map(fn (EccReflexao $r): array => $this->serialize($r))
            ->values()
            ->all();
    }

    public function find(string $id): EccReflexao
    {
        $this->assertCanView();

        $reflexao = EccReflexao::query()
            ->where('igreja_id', $this->igrejaId())
            ->whereKey($id)
            ->firstOrFail();

        if (
            ! $this->visibility->userCan('ecc.reflexoes.manage')
            && $reflexao->status !== EccReflexao::STATUS_PUBLICADO
        ) {
            abort(404);
        }

        return $reflexao;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, ?UploadedFile $imagem, User $user): EccReflexao
    {
        $this->assertCanManage();
        $payload = $this->normalizePayload($data);

        if ($imagem !== null) {
            $payload['imagem_path'] = $this->storeImagem($imagem);
        }

        return EccReflexao::query()->create([
            ...$payload,
            'igreja_id' => $this->igrejaId(),
            'criado_por_user_id' => $user->id,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(EccReflexao $reflexao, array $data, ?UploadedFile $imagem, bool $removerImagem): EccReflexao
    {
        $this->assertCanManage();
        abort_unless((string) $reflexao->igreja_id === $this->igrejaId(), 404);

        $payload = $this->normalizePayload($data, $reflexao);

        if ($removerImagem && $reflexao->imagem_path) {
            $this->deleteImagem($reflexao->imagem_path);
            $payload['imagem_path'] = null;
        }

        if ($imagem !== null) {
            if ($reflexao->imagem_path) {
                $this->deleteImagem($reflexao->imagem_path);
            }
            $payload['imagem_path'] = $this->storeImagem($imagem);
        }

        $reflexao->fill($payload);
        $reflexao->save();

        return $reflexao->refresh();
    }

    public function delete(EccReflexao $reflexao): void
    {
        $this->assertCanManage();
        abort_unless((string) $reflexao->igreja_id === $this->igrejaId(), 404);

        if ($reflexao->imagem_path) {
            $this->deleteImagem($reflexao->imagem_path);
        }

        $reflexao->delete();
    }

    /**
     * @return array<string, mixed>
     */
    public function serialize(EccReflexao $reflexao): array
    {
        $temImagem = $reflexao->imagem_path !== null && $reflexao->imagem_path !== '';

        return [
            'id' => (string) $reflexao->id,
            'titulo' => (string) $reflexao->titulo,
            'passagem_biblica' => $reflexao->passagem_biblica,
            'texto' => (string) $reflexao->texto,
            'tem_imagem' => $temImagem,
            'imagem_url' => $temImagem ? $this->imagemUrl($reflexao) : null,
            'publicado_em' => $reflexao->publicado_em?->format('Y-m-d'),
            'status' => (string) $reflexao->status,
            'criado_por_user_id' => $reflexao->criado_por_user_id ? (string) $reflexao->criado_por_user_id : null,
            'created_at' => $reflexao->created_at?->toIso8601String(),
            'updated_at' => $reflexao->updated_at?->toIso8601String(),
        ];
    }

    private function imagemUrl(EccReflexao $reflexao): ?string
    {
        $tenantSlug = tenant('slug');
        if (! is_string($tenantSlug) || $tenantSlug === '') {
            return null;
        }

        return URL::temporarySignedRoute(
            'ecc.reflexoes.imagem.show',
            now()->addHours(12),
            [
                'id' => $reflexao->id,
                'tenant' => $tenantSlug,
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalizePayload(array $data, ?EccReflexao $existing = null): array
    {
        $status = (string) ($data['status'] ?? $existing?->status ?? EccReflexao::STATUS_RASCUNHO);

        $publicadoEm = array_key_exists('publicado_em', $data)
            ? $this->parseNullableDate($data['publicado_em'])
            : $existing?->publicado_em;

        if ($status === EccReflexao::STATUS_PUBLICADO && $publicadoEm === null) {
            $publicadoEm = now()->startOfDay();
        }

        $passagem = array_key_exists('passagem_biblica', $data)
            ? ($data['passagem_biblica'] !== null && $data['passagem_biblica'] !== ''
                ? (string) $data['passagem_biblica']
                : null)
            : $existing?->passagem_biblica;

        return [
            'titulo' => (string) ($data['titulo'] ?? $existing?->titulo),
            'passagem_biblica' => $passagem,
            'texto' => (string) ($data['texto'] ?? $existing?->texto),
            'publicado_em' => $publicadoEm,
            'status' => $status,
        ];
    }

    private function parseNullableDate(mixed $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        return Carbon::parse((string) $value)->startOfDay();
    }

    private function storeImagem(UploadedFile $file): string
    {
        $mime = (string) $file->getMimeType();
        if (! in_array($mime, ['image/jpeg', 'image/png', 'image/webp', 'image/jpg'], true)) {
            throw ValidationException::withMessages([
                'imagem' => ['Use JPG, PNG ou WebP.'],
            ]);
        }

        if ($file->getSize() > self::MAX_IMAGE_KB * 1024) {
            throw ValidationException::withMessages([
                'imagem' => ['Imagem deve ter no máximo 5 MB.'],
            ]);
        }

        $dir = 'ecc/reflexoes/'.$this->igrejaId();

        return $file->store($dir, 'public');
    }

    private function deleteImagem(string $path): void
    {
        if ($path !== '' && Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }

    private function assertCanView(): void
    {
        abort_unless(
            $this->visibility->userCan('ecc.reflexoes.view')
                || $this->visibility->userCan('ecc.reflexoes.manage'),
            403,
        );
    }

    private function assertCanManage(): void
    {
        abort_unless($this->visibility->userCan('ecc.reflexoes.manage'), 403);
    }

    private function igrejaId(): string
    {
        return (string) $this->igrejaContext->current()->id;
    }
}
