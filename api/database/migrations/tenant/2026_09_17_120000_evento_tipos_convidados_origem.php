<?php

declare(strict_types=1);

use App\Models\EventoTipo;
use App\Models\Igreja;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evento_tipos', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('igreja_id')->constrained('igrejas')->cascadeOnDelete();
            $table->string('codigo', 64);
            $table->string('nome');
            $table->string('abrev', 16)->nullable();
            $table->string('cor', 32)->nullable();
            $table->boolean('permite_compras')->default(false);
            $table->string('escopo', 16)->default('ambos'); // ecc|geral|ambos
            $table->unsignedInteger('ordem')->default(0);
            $table->timestamps();

            $table->unique(['igreja_id', 'codigo']);
        });

        Schema::table('ecc_eventos', function (Blueprint $table) {
            $table->foreignUlid('evento_tipo_id')->nullable()->after('tipo')->constrained('evento_tipos')->nullOnDelete();
            $table->string('origem', 16)->default('ecc')->after('evento_tipo_id');
            $table->index(['igreja_id', 'origem']);
        });

        Schema::table('ecc_evento_casal', function (Blueprint $table) {
            $table->unsignedInteger('convidados')->default(0)->after('casal_id');
        });

        $defaults = EventoTipo::defaultsCatalog();

        foreach (Igreja::query()->pluck('id') as $igrejaId) {
            $map = [];
            foreach ($defaults as $i => $row) {
                $id = (string) \Illuminate\Support\Str::ulid();
                DB::table('evento_tipos')->insert([
                    'id' => $id,
                    'igreja_id' => $igrejaId,
                    'codigo' => $row['codigo'],
                    'nome' => $row['nome'],
                    'abrev' => $row['abrev'],
                    'cor' => $row['cor'],
                    'permite_compras' => $row['permite_compras'],
                    'escopo' => $row['escopo'],
                    'ordem' => $i,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $map[$row['codigo']] = $id;
            }

            foreach ($map as $codigo => $tipoId) {
                DB::table('ecc_eventos')
                    ->where('igreja_id', $igrejaId)
                    ->where('tipo', $codigo)
                    ->update(['evento_tipo_id' => $tipoId]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('ecc_evento_casal', function (Blueprint $table) {
            $table->dropColumn('convidados');
        });

        Schema::table('ecc_eventos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('evento_tipo_id');
            $table->dropColumn('origem');
        });

        Schema::dropIfExists('evento_tipos');
    }
};
