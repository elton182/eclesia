<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calendario_observacoes', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('calendario_mensal_id')->constrained('calendario_mensais')->cascadeOnDelete();
            $table->string('titulo');
            $table->text('descricao');
            $table->unsignedSmallInteger('ordem')->default(0);
            $table->timestamps();

            $table->index(['calendario_mensal_id', 'ordem']);
        });

        Schema::create('calendario_tempos_liturgicos', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('calendario_mensal_id')->constrained('calendario_mensais')->cascadeOnDelete();
            $table->date('data_domingo');
            $table->string('rotulo')->nullable();
            $table->timestamps();

            $table->unique(['calendario_mensal_id', 'data_domingo'], 'cal_tempo_liturgico_unique');
        });

        Schema::table('calendario_itens', function (Blueprint $table) {
            $table->foreignUlid('observacao_id')
                ->nullable()
                ->after('notas')
                ->constrained('calendario_observacoes')
                ->nullOnDelete();
        });

        if (Schema::hasColumn('calendario_mensais', 'observacoes_fixas')) {
            $mensais = DB::table('calendario_mensais')
                ->whereNotNull('observacoes_fixas')
                ->where('observacoes_fixas', '!=', '')
                ->get(['id', 'observacoes_fixas']);

            $now = now();
            foreach ($mensais as $mensal) {
                $linhas = preg_split('/\r\n|\r|\n/', (string) $mensal->observacoes_fixas) ?: [];
                $ordem = 0;
                foreach ($linhas as $linha) {
                    $texto = trim($linha);
                    if ($texto === '') {
                        continue;
                    }
                    $ordem++;
                    DB::table('calendario_observacoes')->insert([
                        'id' => (string) Str::ulid(),
                        'calendario_mensal_id' => $mensal->id,
                        'titulo' => 'Observação '.$ordem,
                        'descricao' => $texto,
                        'ordem' => $ordem,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }

            Schema::table('calendario_mensais', function (Blueprint $table) {
                $table->dropColumn('observacoes_fixas');
            });
        }
    }

    public function down(): void
    {
        Schema::table('calendario_itens', function (Blueprint $table) {
            $table->dropConstrainedForeignId('observacao_id');
        });

        Schema::dropIfExists('calendario_tempos_liturgicos');
        Schema::dropIfExists('calendario_observacoes');

        if (! Schema::hasColumn('calendario_mensais', 'observacoes_fixas')) {
            Schema::table('calendario_mensais', function (Blueprint $table) {
                $table->text('observacoes_fixas')->nullable()->after('subtitulo');
            });
        }
    }
};
