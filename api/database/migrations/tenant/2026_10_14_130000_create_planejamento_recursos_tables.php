<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('planejamento_recursos')) {
            Schema::create('planejamento_recursos', function (Blueprint $table) {
                $table->ulid('id')->primary();
                $table->foreignUlid('igreja_id')->constrained('igrejas')->cascadeOnDelete();
                $table->string('nome', 120);
                $table->string('tipo', 16);
                $table->unsignedInteger('quantidade')->default(1);
                $table->text('descricao')->nullable();
                $table->boolean('ativo')->default(true);
                $table->unsignedInteger('ordem')->default(0);
                $table->timestamps();

                $table->unique(['igreja_id', 'nome']);
                $table->index(['igreja_id', 'tipo', 'ativo']);
            });
        }

        if (! Schema::hasTable('planejamento_evento_recurso')) {
            Schema::create('planejamento_evento_recurso', function (Blueprint $table) {
                $table->foreignUlid('planejamento_evento_id')
                    ->constrained('planejamento_eventos')
                    ->cascadeOnDelete();
                $table->foreignUlid('planejamento_recurso_id')
                    ->constrained('planejamento_recursos')
                    ->cascadeOnDelete();
                $table->unsignedInteger('quantidade')->default(1);

                $table->primary(['planejamento_evento_id', 'planejamento_recurso_id'], 'plan_evento_recurso_pk');
                $table->index('planejamento_recurso_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('planejamento_evento_recurso');
        Schema::dropIfExists('planejamento_recursos');
    }
};
