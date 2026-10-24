<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ecc_perguntas')) {
            Schema::create('ecc_perguntas', function (Blueprint $table) {
                $table->ulid('id')->primary();
                $table->foreignUlid('igreja_id')->constrained('igrejas')->cascadeOnDelete();
                $table->string('titulo', 180);
                $table->text('descricao')->nullable();
                $table->string('status', 20)->default('rascunho');
                $table->foreignId('responsavel_user_id')->constrained('users')->cascadeOnDelete();
                $table->timestamp('disponibilizado_em')->nullable();
                $table->foreignId('criado_por_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['igreja_id', 'status', 'disponibilizado_em']);
            });
        }

        if (! Schema::hasTable('ecc_pergunta_campos')) {
            Schema::create('ecc_pergunta_campos', function (Blueprint $table) {
                $table->ulid('id')->primary();
                $table->foreignUlid('pergunta_id')->constrained('ecc_perguntas')->cascadeOnDelete();
                $table->unsignedSmallInteger('ordem')->default(0);
                $table->string('tipo', 20);
                $table->string('rotulo', 180);
                $table->boolean('obrigatorio')->default(true);
                $table->json('opcoes')->nullable();
                $table->timestamps();

                $table->index(['pergunta_id', 'ordem']);
            });
        }

        if (! Schema::hasTable('ecc_pergunta_envios')) {
            Schema::create('ecc_pergunta_envios', function (Blueprint $table) {
                $table->ulid('id')->primary();
                $table->foreignUlid('pergunta_id')->constrained('ecc_perguntas')->cascadeOnDelete();
                $table->foreignUlid('equipe_id')->constrained('ecc_equipes')->cascadeOnDelete();
                $table->foreignId('enviado_por_user_id')->constrained('users')->cascadeOnDelete();
                $table->json('respostas');
                $table->string('status', 20)->default('enviado');
                $table->timestamp('enviado_em');
                $table->text('resposta_responsavel')->nullable();
                $table->timestamp('respondido_em')->nullable();
                $table->foreignId('respondido_por_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->unique(['pergunta_id', 'equipe_id']);
                $table->index(['pergunta_id', 'status']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ecc_pergunta_envios');
        Schema::dropIfExists('ecc_pergunta_campos');
        Schema::dropIfExists('ecc_perguntas');
    }
};
