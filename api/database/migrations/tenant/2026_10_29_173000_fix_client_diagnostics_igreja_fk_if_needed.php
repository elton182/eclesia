<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Corrige tenants em que a create usou foreignId(bigint) → igrejas.id(ulid)
 * e falhou no ADD CONSTRAINT (errno 150), deixando igreja_id como bigint.
 * Normaliza para CHAR(26) nullable indexado, sem FK.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('client_diagnostics')) {
            return;
        }

        // Remove FK se existir (nome padrão Laravel).
        try {
            Schema::table('client_diagnostics', function (Blueprint $table): void {
                $table->dropForeign(['igreja_id']);
            });
        } catch (\Throwable) {
            // ignore — constraint pode não existir
        }

        try {
            DB::statement('ALTER TABLE client_diagnostics DROP FOREIGN KEY client_diagnostics_igreja_id_foreign');
        } catch (\Throwable) {
            // ignore
        }

        // bigint → ULID (CHAR(26)); tabela parcial em prod costuma estar vazia.
        $driver = Schema::getConnection()->getDriverName();
        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement('ALTER TABLE client_diagnostics MODIFY igreja_id CHAR(26) NULL');
        } elseif ($driver === 'sqlite') {
            // SQLite de teste: tipo é afrouxado; nada a modificar.
        }

        Schema::table('client_diagnostics', function (Blueprint $table): void {
            $sm = Schema::getConnection()->getSchemaBuilder();
            $indexes = $sm->getIndexes('client_diagnostics');
            $hasIndex = false;
            foreach ($indexes as $index) {
                $cols = $index['columns'] ?? [];
                if ($cols === ['igreja_id']) {
                    $hasIndex = true;
                    break;
                }
            }
            if (! $hasIndex) {
                $table->index('igreja_id');
            }
        });
    }

    public function down(): void
    {
        // Não recria o FK problemático.
    }
};
