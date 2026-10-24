<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ecc_recados') && ! Schema::hasColumn('ecc_recados', 'anexo_path')) {
            Schema::table('ecc_recados', function (Blueprint $table) {
                $table->string('anexo_path')->nullable()->after('corpo');
                $table->string('anexo_nome', 255)->nullable()->after('anexo_path');
                $table->string('anexo_mime', 120)->nullable()->after('anexo_nome');
                $table->unsignedInteger('anexo_tamanho')->nullable()->after('anexo_mime');
            });
        }

        if (Schema::hasTable('ecc_reflexoes') && ! Schema::hasColumn('ecc_reflexoes', 'anexo_path')) {
            Schema::table('ecc_reflexoes', function (Blueprint $table) {
                $table->string('anexo_path')->nullable()->after('texto');
                $table->string('anexo_nome', 255)->nullable()->after('anexo_path');
                $table->string('anexo_mime', 120)->nullable()->after('anexo_nome');
                $table->unsignedInteger('anexo_tamanho')->nullable()->after('anexo_mime');
            });
        }

        if (! Schema::hasTable('ecc_leituras')) {
            Schema::create('ecc_leituras', function (Blueprint $table) {
                $table->ulid('id')->primary();
                $table->foreignUlid('igreja_id')->constrained('igrejas')->cascadeOnDelete();
                $table->string('alvo_tipo', 20);
                $table->ulid('alvo_id');
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->timestamp('lido_em');
                $table->timestamps();

                $table->unique(['igreja_id', 'alvo_tipo', 'alvo_id', 'user_id'], 'ecc_leituras_unique');
                $table->index(['igreja_id', 'alvo_tipo', 'alvo_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ecc_leituras');

        if (Schema::hasTable('ecc_recados') && Schema::hasColumn('ecc_recados', 'anexo_path')) {
            Schema::table('ecc_recados', function (Blueprint $table) {
                $table->dropColumn(['anexo_path', 'anexo_nome', 'anexo_mime', 'anexo_tamanho']);
            });
        }

        if (Schema::hasTable('ecc_reflexoes') && Schema::hasColumn('ecc_reflexoes', 'anexo_path')) {
            Schema::table('ecc_reflexoes', function (Blueprint $table) {
                $table->dropColumn(['anexo_path', 'anexo_nome', 'anexo_mime', 'anexo_tamanho']);
            });
        }
    }
};
