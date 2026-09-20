<?php

declare(strict_types=1);

use App\Models\EccEquipeServico;
use App\Models\Igreja;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pessoas', function (Blueprint $table) {
            $table->text('nome_usual')->nullable()->after('telefone');
            $table->text('profissao')->nullable()->after('nome_usual');
            $table->text('religiao')->nullable()->after('profissao');
            $table->text('endereco_profissional')->nullable()->after('religiao');
            $table->text('telefone_profissional')->nullable()->after('endereco_profissional');
        });

        Schema::table('casais', function (Blueprint $table) {
            $table->text('engajamento_paroquial')->nullable()->after('observacoes');
            $table->text('habilidades')->nullable()->after('engajamento_paroquial');
        });

        Schema::create('ecc_equipes_servico', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('igreja_id')->constrained('igrejas')->cascadeOnDelete();
            $table->string('nome');
            $table->string('slug', 100);
            $table->unsignedInteger('ordem')->default(0);
            $table->boolean('ativo')->default(true);
            $table->timestamps();

            $table->unique(['igreja_id', 'slug']);
        });

        Schema::create('ecc_casal_etapas', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('casal_id')->constrained('casais')->cascadeOnDelete();
            $table->unsignedTinyInteger('etapa');
            $table->string('ecc_numero', 20)->nullable();
            $table->date('data')->nullable();
            $table->string('local', 255)->nullable();
            $table->timestamps();

            $table->unique(['casal_id', 'etapa']);
        });

        Schema::create('ecc_casal_atividades', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('casal_id')->constrained('casais')->cascadeOnDelete();
            $table->string('ecc_numero', 20);
            $table->foreignUlid('ecc_equipe_servico_id')->constrained('ecc_equipes_servico')->cascadeOnDelete();
            $table->string('status', 5);
            $table->text('observacao')->nullable();
            $table->timestamps();

            $table->unique(['casal_id', 'ecc_numero', 'ecc_equipe_servico_id'], 'ecc_casal_atividades_unique');
        });

        Schema::create('ecc_casal_preferencias', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('casal_id')->constrained('casais')->cascadeOnDelete();
            $table->foreignUlid('ecc_equipe_servico_id')->constrained('ecc_equipes_servico')->cascadeOnDelete();
            $table->unsignedInteger('ordem')->nullable();
            $table->timestamps();

            $table->unique(['casal_id', 'ecc_equipe_servico_id'], 'ecc_casal_preferencias_unique');
        });

        foreach (Igreja::query()->pluck('id') as $igrejaId) {
            EccEquipeServico::seedDefaultsForIgreja((string) $igrejaId);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ecc_casal_preferencias');
        Schema::dropIfExists('ecc_casal_atividades');
        Schema::dropIfExists('ecc_casal_etapas');
        Schema::dropIfExists('ecc_equipes_servico');

        Schema::table('casais', function (Blueprint $table) {
            $table->dropColumn(['engajamento_paroquial', 'habilidades']);
        });

        Schema::table('pessoas', function (Blueprint $table) {
            $table->dropColumn([
                'nome_usual',
                'profissao',
                'religiao',
                'endereco_profissional',
                'telefone_profissional',
            ]);
        });
    }
};
