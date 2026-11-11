<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('planejamento_anual_tokens')) {
            return;
        }

        Schema::create('planejamento_anual_tokens', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('planejamento_anual_id')
                ->unique()
                ->constrained('planejamento_anuais')
                ->cascadeOnDelete();
            $table->string('token_hash', 64)->unique();
            $table->string('token_preview', 16);
            $table->text('token_encrypted')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('revogado_em')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('planejamento_anual_tokens');
    }
};
