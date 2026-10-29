<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Em produção a create antiga usou foreignId (bigint) → igrejas.id (ulid)
        // e falhou no FK (errno 150), deixando a tabela parcial. Evita recriar.
        if (Schema::hasTable('client_diagnostics')) {
            return;
        }

        Schema::create('client_diagnostics', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            // ULID sem FK: igrejas.id é ulid (não bigint). Relação só no app.
            $table->ulid('igreja_id')->nullable()->index();
            $table->string('motivo', 40)->default('geral');
            $table->text('nota')->nullable();
            $table->json('meta');
            $table->json('events');
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['created_at']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_diagnostics');
    }
};
