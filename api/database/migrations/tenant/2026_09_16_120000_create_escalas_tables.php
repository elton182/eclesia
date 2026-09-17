<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evento_agenda', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('igreja_id')->constrained('igrejas')->cascadeOnDelete();
            $table->string('titulo');
            $table->dateTime('inicia_em');
            $table->dateTime('termina_em')->nullable();
            $table->string('local')->nullable();
            $table->string('dono_modulo', 64);
            $table->string('tipo', 64);
            $table->string('referencia_tipo', 64)->nullable();
            $table->ulid('referencia_id')->nullable();
            $table->timestamps();

            $table->index(['igreja_id', 'inicia_em']);
            $table->index(['dono_modulo', 'referencia_tipo', 'referencia_id']);
        });

        Schema::create('escala_tipos', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('igreja_id')->constrained('igrejas')->cascadeOnDelete();
            $table->string('nome');
            $table->text('descricao')->nullable();
            $table->string('unidade_preferida', 16)->default('ambos');
            $table->string('recorrencia', 16)->default('avulsa');
            $table->timestamps();

            $table->unique(['igreja_id', 'nome']);
        });

        Schema::create('escala_equipes', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('igreja_id')->constrained('igrejas')->cascadeOnDelete();
            $table->foreignUlid('escala_tipo_id')->constrained('escala_tipos')->cascadeOnDelete();
            $table->string('nome');
            $table->string('cor', 32)->nullable();
            $table->unsignedInteger('ordem')->default(0);
            $table->unsignedInteger('vagas_sugeridas')->nullable();
            $table->timestamps();

            $table->unique(['escala_tipo_id', 'nome']);
        });

        Schema::create('escala_ocorrencias', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('igreja_id')->constrained('igrejas')->cascadeOnDelete();
            $table->foreignUlid('escala_tipo_id')->constrained('escala_tipos')->cascadeOnDelete();
            $table->string('titulo')->nullable();
            $table->dateTime('inicia_em');
            $table->dateTime('termina_em')->nullable();
            $table->string('local')->nullable();
            $table->foreignUlid('evento_agenda_id')->nullable()->constrained('evento_agenda')->nullOnDelete();
            $table->timestamps();

            $table->index(['igreja_id', 'inicia_em']);
        });

        Schema::create('escala_atribuicoes', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('igreja_id')->constrained('igrejas')->cascadeOnDelete();
            $table->foreignUlid('escala_ocorrencia_id')->constrained('escala_ocorrencias')->cascadeOnDelete();
            $table->foreignUlid('escala_equipe_id')->constrained('escala_equipes')->cascadeOnDelete();
            $table->foreignUlid('pessoa_id')->nullable()->constrained('pessoas')->cascadeOnDelete();
            $table->foreignUlid('casal_id')->nullable()->constrained('casais')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['escala_ocorrencia_id', 'escala_equipe_id', 'pessoa_id'], 'escala_atr_pessoa_unique');
            $table->unique(['escala_ocorrencia_id', 'escala_equipe_id', 'casal_id'], 'escala_atr_casal_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('escala_atribuicoes');
        Schema::dropIfExists('escala_ocorrencias');
        Schema::dropIfExists('escala_equipes');
        Schema::dropIfExists('escala_tipos');
        Schema::dropIfExists('evento_agenda');
    }
};
