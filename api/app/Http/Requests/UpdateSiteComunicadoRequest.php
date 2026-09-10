<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\SiteComunicado;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSiteComunicadoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'titulo' => ['sometimes', 'required', 'string', 'max:255'],
            'resumo' => ['nullable', 'string', 'max:500'],
            'corpo' => ['sometimes', 'required', 'string'],
            'capa_media_id' => ['nullable', 'string', 'exists:site_media,id'],
            'publicado_em' => ['nullable', 'date'],
            'status' => ['sometimes', 'required', 'string', Rule::in([SiteComunicado::STATUS_RASCUNHO, SiteComunicado::STATUS_PUBLICADO])],
            'destaque' => ['sometimes', 'boolean'],
            'igreja_id' => ['nullable', 'string', 'exists:igrejas,id'],
        ];
    }
}
