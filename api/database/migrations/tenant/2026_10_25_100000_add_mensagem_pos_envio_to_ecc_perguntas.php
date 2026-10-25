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
            if (! Schema::hasColumn('ecc_perguntas', 'mensagem_pos_envio')) {
                $table->text('mensagem_pos_envio')->nullable()->after('descricao');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('ecc_perguntas')) {
            return;
        }

        Schema::table('ecc_perguntas', function (Blueprint $table) {
            if (Schema::hasColumn('ecc_perguntas', 'mensagem_pos_envio')) {
                $table->dropColumn('mensagem_pos_envio');
            }
        });
    }
};
