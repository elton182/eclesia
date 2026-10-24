<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

final class EccMuralAnexo
{
    private const MAX_KB = 10240;

    /** @var list<string> */
    private const MIMES = [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'image/jpeg',
        'image/png',
        'image/webp',
        'image/jpg',
    ];

    /**
     * @return array{anexo_path: string, anexo_nome: string, anexo_mime: string, anexo_tamanho: int}
     */
    public static function store(UploadedFile $file, string $igrejaId, string $subdir): array
    {
        $mime = (string) ($file->getMimeType() ?: '');
        if (! in_array($mime, self::MIMES, true)) {
            throw ValidationException::withMessages([
                'anexo' => ['Formato não permitido. Use PDF, DOC, DOCX, XLS, XLSX, JPG, PNG ou WebP.'],
            ]);
        }

        if ($file->getSize() > self::MAX_KB * 1024) {
            throw ValidationException::withMessages([
                'anexo' => ['Arquivo deve ter no máximo 10 MB.'],
            ]);
        }

        $path = $file->store('ecc/'.$subdir.'/'.$igrejaId, 'public');

        return [
            'anexo_path' => $path,
            'anexo_nome' => (string) $file->getClientOriginalName(),
            'anexo_mime' => $mime,
            'anexo_tamanho' => (int) $file->getSize(),
        ];
    }

    public static function delete(?string $path): void
    {
        if (is_string($path) && $path !== '' && Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }

    /**
     * @param  object{anexo_path?: ?string, anexo_nome?: ?string, anexo_mime?: ?string, anexo_tamanho?: ?int}  $model
     * @return array{tem_anexo: bool, anexo_nome: ?string, anexo_mime: ?string, anexo_tamanho: ?int}
     */
    public static function meta(object $model): array
    {
        $tem = is_string($model->anexo_path ?? null) && $model->anexo_path !== '';

        return [
            'tem_anexo' => $tem,
            'anexo_nome' => $tem ? ($model->anexo_nome ?? null) : null,
            'anexo_mime' => $tem ? ($model->anexo_mime ?? null) : null,
            'anexo_tamanho' => $tem && isset($model->anexo_tamanho) ? (int) $model->anexo_tamanho : null,
        ];
    }
}
