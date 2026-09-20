<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('casais', function (Blueprint $table) {
            $table->dropColumn([
                'experiencia_servico',
                'preferencia_funcao',
                'etapa_2',
                'etapa_3',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('casais', function (Blueprint $table) {
            $table->text('experiencia_servico')->nullable()->after('ecc_origem');
            $table->text('preferencia_funcao')->nullable()->after('experiencia_servico');
            $table->string('etapa_2', 50)->nullable()->after('ficha_com_foto');
            $table->string('etapa_3', 50)->nullable()->after('etapa_2');
        });
    }
};
