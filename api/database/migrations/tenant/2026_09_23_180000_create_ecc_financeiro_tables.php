<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ecc_financeiro_contas', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('igreja_id')->constrained('igrejas')->cascadeOnDelete();
            $table->string('nome');
            $table->string('tipo', 16); // banco | especie
            $table->unsignedInteger('ordem')->default(0);
            $table->boolean('ativa')->default(true);
            $table->timestamps();

            $table->index(['igreja_id', 'ativa']);
        });

        Schema::create('ecc_financeiro_lancamentos', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('igreja_id')->constrained('igrejas')->cascadeOnDelete();
            $table->foreignUlid('ecc_financeiro_conta_id')->constrained('ecc_financeiro_contas')->restrictOnDelete();
            $table->date('data');
            $table->text('historico');
            $table->string('tipo', 16); // entrada | saida
            $table->decimal('valor', 12, 2);
            $table->ulid('transferencia_id')->nullable();
            $table->boolean('abertura')->default(false);
            $table->timestamps();

            $table->index(['igreja_id', 'data']);
            $table->index(['ecc_financeiro_conta_id', 'data']);
            $table->index('transferencia_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ecc_financeiro_lancamentos');
        Schema::dropIfExists('ecc_financeiro_contas');
    }
};
