<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ecc_eventos', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('igreja_id')->constrained('igrejas')->cascadeOnDelete();
            $table->string('titulo');
            $table->string('tipo', 32);
            $table->dateTime('inicia_em');
            $table->dateTime('termina_em')->nullable();
            $table->string('local')->nullable();
            $table->foreignUlid('casal_compras_id')->nullable()->constrained('casais')->nullOnDelete();
            $table->foreignUlid('evento_agenda_id')->nullable()->constrained('evento_agenda')->nullOnDelete();
            $table->timestamps();

            $table->index(['igreja_id', 'inicia_em']);
            $table->index(['igreja_id', 'tipo']);
        });

        Schema::create('ecc_evento_casal', function (Blueprint $table) {
            $table->foreignUlid('ecc_evento_id')->constrained('ecc_eventos')->cascadeOnDelete();
            $table->foreignUlid('casal_id')->constrained('casais')->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['ecc_evento_id', 'casal_id']);
        });

        Schema::create('ecc_itens_compra', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('ecc_evento_id')->constrained('ecc_eventos')->cascadeOnDelete();
            $table->string('nome');
            $table->decimal('qtd', 12, 2)->default(1);
            $table->string('unidade', 32)->default('un');
            $table->string('status', 16)->default('pendente');
            $table->foreignUlid('doador_casal_id')->nullable()->constrained('casais')->nullOnDelete();
            $table->decimal('valor_gasto', 12, 2)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ecc_itens_compra');
        Schema::dropIfExists('ecc_evento_casal');
        Schema::dropIfExists('ecc_eventos');
    }
};
