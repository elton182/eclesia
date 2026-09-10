<?php

declare(strict_types=1);

namespace App\Models;

use ESolution\DBEncryption\Traits\EncryptedAttribute;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SiteFormSubmission extends Model
{
    use EncryptedAttribute;
    use HasUlids;

    protected $table = 'site_form_submissions';

    /**
     * @var list<string>
     */
    protected $encryptable = [
        'payload',
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'site_form_id',
        'payload',
        'ip',
        'user_agent',
    ];

    public function form(): BelongsTo
    {
        return $this->belongsTo(SiteForm::class, 'site_form_id');
    }

    /**
     * @return array<string, mixed>
     */
    public function payloadArray(): array
    {
        $raw = $this->payload;
        if (! is_string($raw) || $raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }
}
