<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pastoral_user')) {
            Schema::create('pastoral_user', function (Blueprint $table) {
                $table->id();
                $table->foreignUlid('pastoral_id')->constrained('pastorais')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('papel', 20); // coordenador|membro
                $table->timestamps();

                $table->unique(['pastoral_id', 'user_id']);
            });
        }

        if (! Schema::hasTable('planejamento_anuais')) {
            Schema::create('planejamento_anuais', function (Blueprint $table) {
                $table->ulid('id')->primary();
                $table->foreignUlid('igreja_id')->constrained('igrejas')->cascadeOnDelete();
                $table->unsignedSmallInteger('ano');
                $table->string('status', 20)->default('rascunho'); // rascunho|coleta|revisao|fechado
                $table->timestamp('fechado_em')->nullable();
                $table->timestamps();

                $table->unique(['igreja_id', 'ano']);
            });
        }

        if (! Schema::hasTable('planejamento_eventos')) {
            Schema::create('planejamento_eventos', function (Blueprint $table) {
                $table->ulid('id')->primary();
                $table->foreignUlid('planejamento_anual_id')->constrained('planejamento_anuais')->cascadeOnDelete();
                $table->foreignUlid('pastoral_id')->constrained('pastorais')->cascadeOnDelete();
                $table->string('titulo');
                $table->date('data_inicio');
                $table->date('data_fim')->nullable();
                $table->time('hora_inicio')->nullable();
                $table->time('hora_fim')->nullable();
                $table->unsignedInteger('participantes_media')->nullable();
                $table->string('recorrencia_texto')->nullable();
                $table->text('observacoes')->nullable();
                $table->string('local_texto')->nullable();
                $table->string('status_solicitacao', 30)->default('proposta');
                $table->text('motivo_ajuste')->nullable();
                $table->timestamps();

                $table->index(['planejamento_anual_id', 'data_inicio']);
                $table->index(['pastoral_id', 'status_solicitacao']);
            });
        }

        if (! Schema::hasTable('planejamento_evento_local')) {
            Schema::create('planejamento_evento_local', function (Blueprint $table) {
                $table->foreignUlid('planejamento_evento_id')
                    ->constrained('planejamento_eventos')
                    ->cascadeOnDelete();
                $table->foreignUlid('calendario_local_id')
                    ->constrained('calendario_locais')
                    ->cascadeOnDelete();

                $table->primary(['planejamento_evento_id', 'calendario_local_id'], 'pe_local_pk');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('planejamento_evento_local');
        Schema::dropIfExists('planejamento_eventos');
        Schema::dropIfExists('planejamento_anuais');
        Schema::dropIfExists('pastoral_user');
    }
};
