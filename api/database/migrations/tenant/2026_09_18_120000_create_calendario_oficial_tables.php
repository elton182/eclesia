<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calendario_locais', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('igreja_id')->constrained('igrejas')->cascadeOnDelete();
            $table->string('nome');
            $table->unsignedSmallInteger('ordem')->default(0);
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });

        Schema::create('calendario_slots_padrao', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('igreja_id')->constrained('igrejas')->cascadeOnDelete();
            $table->foreignUlid('local_id')->constrained('calendario_locais')->cascadeOnDelete();
            $table->unsignedTinyInteger('dia_semana'); // 0=domingo … 6=sábado
            $table->time('hora');
            $table->string('secao', 20)->default('semana'); // fds|semana
            $table->unsignedSmallInteger('ordem')->default(0);
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });

        Schema::create('calendario_mensais', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('igreja_id')->constrained('igrejas')->cascadeOnDelete();
            $table->unsignedSmallInteger('ano');
            $table->unsignedTinyInteger('mes');
            $table->string('status', 20)->default('rascunho'); // rascunho|coleta|montagem|fechado
            $table->string('titulo')->nullable();
            $table->string('subtitulo')->nullable();
            $table->text('observacoes_fixas')->nullable();
            $table->timestamp('fechado_em')->nullable();
            $table->timestamps();

            $table->unique(['igreja_id', 'ano', 'mes']);
        });

        Schema::create('calendario_itens', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('calendario_mensal_id')->constrained('calendario_mensais')->cascadeOnDelete();
            $table->foreignUlid('local_id')->nullable()->constrained('calendario_locais')->nullOnDelete();
            $table->date('data');
            $table->time('hora')->nullable();
            $table->string('secao', 20); // fds|semana|festa|casamento|obs_movel
            $table->string('titulo')->nullable();
            $table->foreignUlid('pessoa_id')->nullable()->constrained('pessoas')->nullOnDelete();
            $table->string('celebrante_nome')->nullable();
            $table->string('notas')->nullable();
            $table->unsignedSmallInteger('ordem')->default(0);
            $table->timestamps();

            $table->index(['calendario_mensal_id', 'secao', 'data']);
        });

        Schema::create('calendario_coleta_links', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('calendario_mensal_id')->constrained('calendario_mensais')->cascadeOnDelete();
            $table->string('token', 64)->nullable()->unique();
            $table->string('token_hash', 64)->unique();
            $table->string('token_preview', 12);
            $table->string('rotulo')->nullable();
            $table->timestamp('expira_em')->nullable();
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });


        Schema::create('calendario_coleta_colaboradores', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('calendario_mensal_id')->constrained('calendario_mensais')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['calendario_mensal_id', 'user_id'], 'cal_coleta_colab_unique');
        });

        Schema::create('calendario_indisponibilidades', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('calendario_mensal_id')->constrained('calendario_mensais')->cascadeOnDelete();
            $table->foreignUlid('coleta_link_id')->nullable()->constrained('calendario_coleta_links')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUlid('pessoa_id')->nullable()->constrained('pessoas')->nullOnDelete();
            $table->string('nome_exibicao');
            $table->date('data');
            $table->string('motivo')->nullable();
            $table->timestamps();

            $table->index(['calendario_mensal_id', 'data']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calendario_indisponibilidades');
        Schema::dropIfExists('calendario_coleta_colaboradores');
        Schema::dropIfExists('calendario_coleta_links');
        Schema::dropIfExists('calendario_itens');
        Schema::dropIfExists('calendario_mensais');
        Schema::dropIfExists('calendario_slots_padrao');
        Schema::dropIfExists('calendario_locais');
    }
};
