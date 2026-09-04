<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('igrejas', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('nome');
            $table->timestamps();
        });

        Schema::create('pessoas', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('igreja_id')->constrained('igrejas')->cascadeOnDelete();
            $table->text('nome');
            $table->text('email')->nullable();
            $table->text('telefone')->nullable();
            $table->date('data_nascimento')->nullable();
            $table->timestamps();
        });

        Schema::create('ecc_equipes', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('igreja_id')->constrained('igrejas')->cascadeOnDelete();
            $table->string('nome');
            $table->string('cor', 32)->nullable();
            $table->timestamps();

            $table->unique(['igreja_id', 'nome']);
        });

        Schema::create('casais', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('igreja_id')->constrained('igrejas')->cascadeOnDelete();
            $table->foreignUlid('ecc_equipe_id')->nullable()->constrained('ecc_equipes')->nullOnDelete();
            $table->foreignUlid('pessoa_a_id')->constrained('pessoas')->cascadeOnDelete();
            $table->foreignUlid('pessoa_b_id')->constrained('pessoas')->cascadeOnDelete();
            $table->text('endereco')->nullable();
            $table->text('bairro')->nullable();
            $table->text('cidade')->nullable();
            $table->string('uf', 2)->nullable();
            $table->text('cep')->nullable();
            $table->date('data_casamento')->nullable();
            $table->text('filhos')->nullable();
            $table->text('observacoes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('casais');
        Schema::dropIfExists('ecc_equipes');
        Schema::dropIfExists('pessoas');
        Schema::dropIfExists('igrejas');
    }
};
