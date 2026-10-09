<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\PlanejamentoAnual;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TransitionPlanejamentoStatusRequest extends FormRequest
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
            'status' => ['required', 'string', Rule::in(PlanejamentoAnual::STATUSES)],
        ];
    }
}
