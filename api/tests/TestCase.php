<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\File;

abstract class TestCase extends BaseTestCase
{
    protected function tenantDatabasePath(string $tenantId): string
    {
        $prefix = (string) config('tenancy.database.prefix');
        $suffix = (string) config('tenancy.database.suffix');

        return database_path($prefix.$tenantId.$suffix);
    }

    protected function deleteTenantDatabase(string $tenantId): void
    {
        $dbPath = $this->tenantDatabasePath($tenantId);
        if (File::exists($dbPath)) {
            File::delete($dbPath);
        }
    }
}
