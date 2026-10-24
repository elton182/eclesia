<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ecc_likes')) {
            Schema::create('ecc_likes', function (Blueprint $table) {
                $table->ulid('id')->primary();
                $table->foreignUlid('igreja_id')->constrained('igrejas')->cascadeOnDelete();
                $table->string('alvo_tipo', 20); // recado|reflexao
                $table->ulid('alvo_id');
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->timestamps();

                $table->unique(['igreja_id', 'alvo_tipo', 'alvo_id', 'user_id'], 'ecc_likes_unique');
                $table->index(['igreja_id', 'alvo_tipo', 'alvo_id']);
            });
        }

        if (! Schema::hasTable('ecc_comentarios')) {
            Schema::create('ecc_comentarios', function (Blueprint $table) {
                $table->ulid('id')->primary();
                $table->foreignUlid('igreja_id')->constrained('igrejas')->cascadeOnDelete();
                $table->string('alvo_tipo', 20);
                $table->ulid('alvo_id');
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->text('corpo');
                $table->string('status', 20)->default('pendente');
                $table->foreignId('revisado_por_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('revisado_em')->nullable();
                $table->string('motivo_revisao', 500)->nullable();
                $table->timestamps();

                $table->index(['igreja_id', 'status', 'created_at']);
                $table->index(['igreja_id', 'alvo_tipo', 'alvo_id', 'status']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ecc_comentarios');
        Schema::dropIfExists('ecc_likes');
    }
};
