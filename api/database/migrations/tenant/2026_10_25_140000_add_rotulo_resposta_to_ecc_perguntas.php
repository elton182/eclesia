<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ecc_perguntas')) {
            return;
        }

        Schema::table('ecc_perguntas', function (Blueprint $table) {
            if (! Schema::hasColumn('ecc_perguntas', 'rotulo_resposta')) {
                $table->string('rotulo_resposta', 120)->nullable()->after('mensagem_pos_envio');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('ecc_perguntas')) {
            return;
        }

        Schema::table('ecc_perguntas', function (Blueprint $table) {
            if (Schema::hasColumn('ecc_perguntas', 'rotulo_resposta')) {
                $table->dropColumn('rotulo_resposta');
            }
        });
    }
};
