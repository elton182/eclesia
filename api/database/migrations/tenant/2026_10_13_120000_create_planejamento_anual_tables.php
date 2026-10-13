<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('planejamento_anuais', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('igreja_id')->constrained('igrejas')->cascadeOnDelete();
            $table->unsignedSmallInteger('ano');
            $table->string('status', 20)->default('aberto'); // aberto|coleta|fechado
            $table->timestamp('aberto_em')->nullable();
            $table->timestamp('coleta_em')->nullable();
            $table->timestamp('fechado_em')->nullable();
            $table->timestamps();

            $table->unique(['igreja_id', 'ano']);
        });

        Schema::create('planejamento_eventos', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('planejamento_anual_id')->constrained('planejamento_anuais')->cascadeOnDelete();
            $table->foreignUlid('pastoral_id')->constrained('pastorais')->cascadeOnDelete();
            $table->string('titulo');
            $table->text('descricao')->nullable();
            $table->dateTime('data_inicio');
            $table->dateTime('data_fim')->nullable();
            $table->boolean('dia_inteiro')->default(true);
            $table->string('status', 20)->default('proposta'); // proposta|em_revisao|aprovado|rejeitado
            $table->string('criado_via', 20)->default('login'); // login|link
            $table->text('notas_revisao')->nullable();
            $table->timestamps();

            $table->index(['planejamento_anual_id', 'data_inicio']);
            $table->index(['planejamento_anual_id', 'pastoral_id']);
            $table->index(['planejamento_anual_id', 'status']);
        });

        Schema::create('planejamento_pastoral_tokens', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('planejamento_anual_id')->constrained('planejamento_anuais')->cascadeOnDelete();
            $table->foreignUlid('pastoral_id')->constrained('pastorais')->cascadeOnDelete();
            $table->string('token', 64); // sha256 hex
            $table->string('token_preview', 12);
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('revogado_em')->nullable();
            $table->timestamps();

            $table->unique('token');
            $table->index(['planejamento_anual_id', 'pastoral_id']);
        });

        Schema::create('pastoral_user', function (Blueprint $table) {
            $table->id();
            $table->foreignUlid('pastoral_id')->constrained('pastorais')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['pastoral_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pastoral_user');
        Schema::dropIfExists('planejamento_pastoral_tokens');
        Schema::dropIfExists('planejamento_eventos');
        Schema::dropIfExists('planejamento_anuais');
    }
};
