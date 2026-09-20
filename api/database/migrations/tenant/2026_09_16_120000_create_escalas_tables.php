<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agenda unificada do núcleo (EventoAgenda).
 * As tabelas escala_* foram removidas (módulo Escalas genéricas descontinuado;
 * ver ADR-0005 superseded e migration drop_escalas_tables).
 */
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
    }

    public function down(): void
    {
        Schema::dropIfExists('evento_agenda');
    }
};
