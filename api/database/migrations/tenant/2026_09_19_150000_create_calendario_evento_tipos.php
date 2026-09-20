<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('calendario_evento_tipos')) {
            Schema::create('calendario_evento_tipos', function (Blueprint $table) {
                $table->ulid('id')->primary();
                $table->string('slug', 40)->unique();
                $table->string('nome', 80);
                $table->string('secao_padrao', 20); // grade|festa|casamento
                $table->boolean('exige_titulo')->default(false);
                $table->unsignedSmallInteger('ordem')->default(0);
                $table->boolean('ativo')->default(true);
                $table->boolean('sistema')->default(true);
                $table->timestamps();
            });
        }

        Schema::table('calendario_itens', function (Blueprint $table) {
            if (! Schema::hasColumn('calendario_itens', 'tipo_id')) {
                $table->foreignUlid('tipo_id')
                    ->nullable()
                    ->after('local_id')
                    ->constrained('calendario_evento_tipos')
                    ->nullOnDelete();
            }
        });

        $defaults = [
            ['slug' => 'missa', 'nome' => 'Missa', 'secao_padrao' => 'grade', 'exige_titulo' => false, 'ordem' => 1],
            ['slug' => 'celebracao', 'nome' => 'Celebração', 'secao_padrao' => 'grade', 'exige_titulo' => false, 'ordem' => 2],
            ['slug' => 'casamento', 'nome' => 'Casamento', 'secao_padrao' => 'casamento', 'exige_titulo' => false, 'ordem' => 3],
            ['slug' => 'batismo', 'nome' => 'Batismo', 'secao_padrao' => 'festa', 'exige_titulo' => false, 'ordem' => 4],
            ['slug' => 'festa', 'nome' => 'Festa', 'secao_padrao' => 'festa', 'exige_titulo' => false, 'ordem' => 5],
            ['slug' => 'outro', 'nome' => 'Outro', 'secao_padrao' => 'festa', 'exige_titulo' => true, 'ordem' => 6],
        ];

        $now = now();
        foreach ($defaults as $row) {
            if (\Illuminate\Support\Facades\DB::table('calendario_evento_tipos')->where('slug', $row['slug'])->exists()) {
                continue;
            }
            \Illuminate\Support\Facades\DB::table('calendario_evento_tipos')->insert([
                'id' => (string) Str::ulid(),
                'slug' => $row['slug'],
                'nome' => $row['nome'],
                'secao_padrao' => $row['secao_padrao'],
                'exige_titulo' => $row['exige_titulo'],
                'ordem' => $row['ordem'],
                'ativo' => true,
                'sistema' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('calendario_itens') && Schema::hasColumn('calendario_itens', 'tipo_id')) {
            Schema::table('calendario_itens', function (Blueprint $table) {
                $table->dropConstrainedForeignId('tipo_id');
            });
        }
        Schema::dropIfExists('calendario_evento_tipos');
    }
};
