<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Igreja;
use App\Models\Pastoral;
use App\Models\SiteBlock;
use App\Models\SiteComunicado;
use App\Models\SiteForm;
use App\Models\SiteFormField;
use App\Models\SiteFormSubmission;
use App\Models\SiteMedia;
use App\Models\SitePage;
use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class SiteService
{
    public function settings(): SiteSetting
    {
        return SiteSetting::query()->firstOrCreate([], [
            'publicado' => false,
            'titulo' => 'Site',
            'menu' => [],
            'cores' => [],
            'seo' => [],
            'contato' => [],
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateSettings(array $data): SiteSetting
    {
        $settings = $this->settings();
        $settings->fill($data);
        $settings->save();

        return $settings->refresh();
    }

    public function assertPublished(): SiteSetting
    {
        $settings = $this->settings();
        if (! $settings->publicado) {
            throw new NotFoundHttpException('Site não publicado.');
        }

        return $settings;
    }

    /**
     * @return array{settings: SiteSetting, page: SitePage|null}
     */
    public function publicHome(): array
    {
        $settings = $this->assertPublished();
        $page = SitePage::query()
            ->with(['blocks' => fn ($q) => $q->where('visivel', true)->orderBy('ordem')])
            ->where('status', SitePage::STATUS_PUBLICADO)
            ->where('is_home', true)
            ->first();

        if ($page === null) {
            $page = SitePage::query()
                ->with(['blocks' => fn ($q) => $q->where('visivel', true)->orderBy('ordem')])
                ->where('status', SitePage::STATUS_PUBLICADO)
                ->where('slug', 'home')
                ->first();
        }

        return ['settings' => $settings, 'page' => $page];
    }

    public function publicPage(string $slug): SitePage
    {
        $this->assertPublished();

        $page = SitePage::query()
            ->with(['blocks' => fn ($q) => $q->where('visivel', true)->orderBy('ordem')])
            ->where('status', SitePage::STATUS_PUBLICADO)
            ->where('slug', $slug)
            ->first();

        if ($page === null) {
            throw new NotFoundHttpException('Página não encontrada.');
        }

        return $page;
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, SitePage>
     */
    public function listPages()
    {
        return SitePage::query()->with('blocks')->orderBy('ordem')->orderBy('titulo')->get();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createPage(array $data): SitePage
    {
        return DB::transaction(function () use ($data) {
            $blocks = $data['blocks'] ?? null;
            unset($data['blocks']);

            if (! empty($data['is_home'])) {
                SitePage::query()->where('is_home', true)->update(['is_home' => false]);
            }

            $page = SitePage::query()->create($data);
            if (is_array($blocks)) {
                $this->syncBlocks($page, $blocks);
            }

            return $page->load('blocks');
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updatePage(SitePage $page, array $data): SitePage
    {
        return DB::transaction(function () use ($page, $data) {
            $blocks = $data['blocks'] ?? null;
            unset($data['blocks']);

            if (! empty($data['is_home'])) {
                SitePage::query()->where('is_home', true)->whereKeyNot($page->id)->update(['is_home' => false]);
            }

            $page->fill($data);
            $page->save();

            if (is_array($blocks)) {
                $this->syncBlocks($page, $blocks);
            }

            return $page->refresh()->load('blocks');
        });
    }

    public function deletePage(SitePage $page): void
    {
        $page->delete();
    }

    /**
     * @param  list<array<string, mixed>>  $blocks
     */
    public function syncBlocks(SitePage $page, array $blocks): void
    {
        $page->blocks()->delete();

        foreach (array_values($blocks) as $index => $block) {
            $tipo = (string) ($block['tipo'] ?? '');
            if (! in_array($tipo, SitePage::BLOCK_TYPES, true)) {
                throw ValidationException::withMessages([
                    "blocks.{$index}.tipo" => ['Tipo de bloco inválido.'],
                ]);
            }

            SiteBlock::query()->create([
                'site_page_id' => $page->id,
                'tipo' => $tipo,
                'ordem' => (int) ($block['ordem'] ?? $index),
                'visivel' => (bool) ($block['visivel'] ?? true),
                'payload' => is_array($block['payload'] ?? null) ? $block['payload'] : [],
            ]);
        }
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, SiteMedia>
     */
    public function listMedia()
    {
        return SiteMedia::query()->orderByDesc('created_at')->get();
    }

    public function storeMedia(UploadedFile $file, ?User $user, ?string $alt = null): SiteMedia
    {
        $path = $file->store('site/media', 'public');

        return SiteMedia::query()->create([
            'path' => $path,
            'alt' => $alt,
            'mime' => $file->getMimeType(),
            'uploaded_by' => $user?->getKey(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createComunicado(array $data): SiteComunicado
    {
        if (($data['status'] ?? '') === SiteComunicado::STATUS_PUBLICADO && empty($data['publicado_em'])) {
            $data['publicado_em'] = now();
        }

        return SiteComunicado::query()->create($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateComunicado(SiteComunicado $comunicado, array $data): SiteComunicado
    {
        if (($data['status'] ?? $comunicado->status) === SiteComunicado::STATUS_PUBLICADO
            && empty($data['publicado_em'])
            && $comunicado->publicado_em === null) {
            $data['publicado_em'] = now();
        }

        $comunicado->fill($data);
        $comunicado->save();

        return $comunicado->refresh();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createPastoral(array $data): Pastoral
    {
        return Pastoral::query()->create($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updatePastoral(Pastoral $pastoral, array $data): Pastoral
    {
        $pastoral->fill($data);
        $pastoral->save();

        return $pastoral->refresh();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createForm(array $data): SiteForm
    {
        return DB::transaction(function () use ($data) {
            $fields = $data['fields'] ?? [];
            unset($data['fields']);
            $form = SiteForm::query()->create($data);
            $this->syncFormFields($form, is_array($fields) ? $fields : []);

            return $form->load('fields');
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateForm(SiteForm $form, array $data): SiteForm
    {
        return DB::transaction(function () use ($form, $data) {
            $fields = $data['fields'] ?? null;
            unset($data['fields']);
            $form->fill($data);
            $form->save();
            if (is_array($fields)) {
                $this->syncFormFields($form, $fields);
            }

            return $form->refresh()->load('fields');
        });
    }

    /**
     * @param  list<array<string, mixed>>  $fields
     */
    public function syncFormFields(SiteForm $form, array $fields): void
    {
        $form->fields()->delete();

        foreach (array_values($fields) as $index => $field) {
            $tipo = (string) ($field['tipo'] ?? 'text');
            if (! in_array($tipo, SiteFormField::TIPOS, true)) {
                throw ValidationException::withMessages([
                    "fields.{$index}.tipo" => ['Tipo de campo inválido.'],
                ]);
            }

            SiteFormField::query()->create([
                'site_form_id' => $form->id,
                'nome' => (string) ($field['nome'] ?? 'campo_'.$index),
                'label' => (string) ($field['label'] ?? 'Campo'),
                'tipo' => $tipo,
                'obrigatorio' => (bool) ($field['obrigatorio'] ?? false),
                'opcoes' => $field['opcoes'] ?? null,
                'ordem' => (int) ($field['ordem'] ?? $index),
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public function submitForm(string $slug, array $values, ?string $honeypot, ?string $ip, ?string $userAgent): SiteFormSubmission
    {
        $this->assertPublished();

        if (is_string($honeypot) && trim($honeypot) !== '') {
            throw ValidationException::withMessages([
                'website' => ['Envio rejeitado.'],
            ]);
        }

        $form = SiteForm::query()->with('fields')->where('slug', $slug)->where('ativo', true)->first();
        if ($form === null) {
            throw new NotFoundHttpException('Formulário não encontrado.');
        }

        $normalized = [];
        foreach ($form->fields as $field) {
            $name = $field->nome;
            $value = $values[$name] ?? null;

            if ($field->obrigatorio && ($value === null || $value === '')) {
                throw ValidationException::withMessages([
                    "values.{$name}" => ["O campo {$field->label} é obrigatório."],
                ]);
            }

            if ($field->tipo === 'email' && is_string($value) && $value !== '' && ! filter_var($value, FILTER_VALIDATE_EMAIL)) {
                throw ValidationException::withMessages([
                    "values.{$name}" => ['E-mail inválido.'],
                ]);
            }

            $normalized[$name] = $value;
        }

        return SiteFormSubmission::query()->create([
            'site_form_id' => $form->id,
            'payload' => json_encode($normalized, JSON_THROW_ON_ERROR),
            'ip' => $ip,
            'user_agent' => $userAgent !== null ? Str::limit($userAgent, 500, '') : null,
        ]);
    }

    /**
     * @return \Illuminate\Support\Collection<int, Igreja>
     */
    public function publicIgrejas()
    {
        $this->assertPublished();

        return Igreja::query()
            ->where('publicado_no_site', true)
            ->whereNotNull('slug')
            ->orderBy('nome')
            ->get();
    }

    public function publicIgreja(string $slug): Igreja
    {
        $this->assertPublished();

        $igreja = Igreja::query()
            ->where('publicado_no_site', true)
            ->where('slug', $slug)
            ->first();

        if ($igreja === null) {
            throw new NotFoundHttpException('Igreja não encontrada.');
        }

        return $igreja;
    }

    /**
     * @return \Illuminate\Support\Collection<int, SiteComunicado>
     */
    public function publicComunicados()
    {
        $this->assertPublished();

        return SiteComunicado::query()
            ->where('status', SiteComunicado::STATUS_PUBLICADO)
            ->orderByDesc('destaque')
            ->orderByDesc('publicado_em')
            ->get();
    }

    public function publicComunicado(string $id): SiteComunicado
    {
        $this->assertPublished();

        $item = SiteComunicado::query()
            ->where('status', SiteComunicado::STATUS_PUBLICADO)
            ->whereKey($id)
            ->first();

        if ($item === null) {
            throw new NotFoundHttpException('Comunicado não encontrado.');
        }

        return $item;
    }

    /**
     * @return \Illuminate\Support\Collection<int, Pastoral>
     */
    public function publicPastorais(?string $igrejaFilter = null)
    {
        $this->assertPublished();

        $query = Pastoral::query()
            ->with('igreja')
            ->where('publicado_no_site', true)
            ->where('ativa', true)
            ->orderBy('ordem')
            ->orderBy('nome');

        if ($igrejaFilter !== null && $igrejaFilter !== '') {
            $igreja = Igreja::query()
                ->where(function ($q) use ($igrejaFilter) {
                    $q->whereKey($igrejaFilter)->orWhere('slug', $igrejaFilter);
                })
                ->first();
            if ($igreja === null) {
                return collect();
            }
            $query->where('igreja_id', $igreja->id);
        }

        return $query->get();
    }
}
