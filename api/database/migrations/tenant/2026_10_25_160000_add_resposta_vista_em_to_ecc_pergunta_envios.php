<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ecc_pergunta_envios')) {
            return;
        }

        Schema::table('ecc_pergunta_envios', function (Blueprint $table) {
            if (! Schema::hasColumn('ecc_pergunta_envios', 'resposta_vista_em')) {
                $table->timestamp('resposta_vista_em')->nullable()->after('respondido_por_user_id');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('ecc_pergunta_envios')) {
            return;
        }

        Schema::table('ecc_pergunta_envios', function (Blueprint $table) {
            if (Schema::hasColumn('ecc_pergunta_envios', 'resposta_vista_em')) {
                $table->dropColumn('resposta_vista_em');
            }
        });
    }
};
