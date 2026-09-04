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
            $table->boolean('piloto')->default(false)->after('observacoes');
            $table->unsignedSmallInteger('anos_casados')->nullable()->after('piloto');
            $table->string('ecc_origem', 100)->nullable()->after('anos_casados');
            $table->text('experiencia_servico')->nullable()->after('ecc_origem');
            $table->text('preferencia_funcao')->nullable()->after('experiencia_servico');
            $table->text('funcao_dirigente')->nullable()->after('preferencia_funcao');
            $table->boolean('foi_coordenador_geral')->default(false)->after('funcao_dirigente');
            $table->boolean('ficha_com_foto')->default(false)->after('foi_coordenador_geral');
            $table->string('etapa_2', 50)->nullable()->after('ficha_com_foto');
            $table->string('etapa_3', 50)->nullable()->after('etapa_2');
        });
    }

    public function down(): void
    {
        Schema::table('casais', function (Blueprint $table) {
            $table->dropColumn([
                'piloto',
                'anos_casados',
                'ecc_origem',
                'experiencia_servico',
                'preferencia_funcao',
                'funcao_dirigente',
                'foi_coordenador_geral',
                'ficha_com_foto',
                'etapa_2',
                'etapa_3',
            ]);
        });
    }
};
