<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ecc_recados')) {
            Schema::create('ecc_recados', function (Blueprint $table) {
                $table->ulid('id')->primary();
                $table->foreignUlid('igreja_id')->constrained('igrejas')->cascadeOnDelete();
                $table->string('titulo', 180);
                $table->text('corpo');
                $table->boolean('urgente')->default(false);
                $table->boolean('fixado')->default(false);
                $table->string('escopo', 20); // paroquia|etapa|equipe
                $table->foreignUlid('equipe_id')->nullable()->constrained('ecc_equipes')->nullOnDelete();
                $table->unsignedTinyInteger('etapa')->nullable();
                $table->timestamp('publicado_em')->nullable();
                $table->timestamp('valido_ate')->nullable();
                $table->string('status', 20)->default('rascunho');
                $table->foreignUlid('criado_por_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['igreja_id', 'status', 'publicado_em']);
                $table->index(['igreja_id', 'escopo', 'equipe_id']);
            });
        }

        if (! Schema::hasTable('ecc_reflexoes')) {
            Schema::create('ecc_reflexoes', function (Blueprint $table) {
                $table->ulid('id')->primary();
                $table->foreignUlid('igreja_id')->constrained('igrejas')->cascadeOnDelete();
                $table->string('titulo', 180);
                $table->string('passagem_biblica', 255)->nullable();
                $table->text('texto');
                $table->string('imagem_path')->nullable();
                $table->date('publicado_em')->nullable();
                $table->string('status', 20)->default('rascunho');
                $table->foreignUlid('criado_por_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['igreja_id', 'status', 'publicado_em']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ecc_reflexoes');
        Schema::dropIfExists('ecc_recados');
    }
};
