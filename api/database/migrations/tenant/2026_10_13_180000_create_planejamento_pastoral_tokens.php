<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('planejamento_pastoral_tokens')) {
            return;
        }

        Schema::create('planejamento_pastoral_tokens', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('planejamento_anual_id')
                ->constrained('planejamento_anuais')
                ->cascadeOnDelete();
            $table->foreignUlid('pastoral_id')
                ->constrained('pastorais')
                ->cascadeOnDelete();
            $table->string('token_hash', 64)->unique();
            $table->string('token_preview', 16);
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('revogado_em')->nullable();
            $table->timestamps();

            $table->unique(['planejamento_anual_id', 'pastoral_id'], 'plan_pastoral_token_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('planejamento_pastoral_tokens');
    }
};
