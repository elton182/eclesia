<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Services\AuditLogger;

trait AuditsActivity
{
    public static function bootAuditsActivity(): void
    {
        static::created(function ($model): void {
            app(AuditLogger::class)->logModelEvent($model, AuditLogger::ACTION_CREATED);
        });

        static::updated(function ($model): void {
            app(AuditLogger::class)->logModelEvent($model, AuditLogger::ACTION_UPDATED);
        });

        static::deleted(function ($model): void {
            app(AuditLogger::class)->logModelEvent($model, AuditLogger::ACTION_DELETED);
        });
    }
}
