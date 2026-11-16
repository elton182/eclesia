<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ecc_casal_equipe', function (Blueprint $table) {
            $table->foreignUlid('casal_id')->constrained('casais')->cascadeOnDelete();
            $table->foreignUlid('ecc_equipe_id')->constrained('ecc_equipes')->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['casal_id', 'ecc_equipe_id']);
        });

        if (Schema::hasTable('casais') && Schema::hasColumn('casais', 'ecc_equipe_id')) {
            $rows = DB::table('casais')
                ->whereNotNull('ecc_equipe_id')
                ->get(['id', 'ecc_equipe_id', 'created_at', 'updated_at']);

            foreach ($rows as $row) {
                DB::table('ecc_casal_equipe')->insertOrIgnore([
                    'casal_id' => $row->id,
                    'ecc_equipe_id' => $row->ecc_equipe_id,
                    'created_at' => $row->created_at ?? now(),
                    'updated_at' => $row->updated_at ?? now(),
                ]);
            }
        }

        Schema::table('casais', function (Blueprint $table) {
            if (! Schema::hasColumn('casais', 'ativo')) {
                $table->boolean('ativo')->default(true);
            }
        });

        Schema::table('ecc_equipes', function (Blueprint $table) {
            if (! Schema::hasColumn('ecc_equipes', 'ativo')) {
                $table->boolean('ativo')->default(true);
            }
        });
    }

    public function down(): void
    {
        Schema::table('ecc_equipes', function (Blueprint $table) {
            if (Schema::hasColumn('ecc_equipes', 'ativo')) {
                $table->dropColumn('ativo');
            }
        });

        Schema::table('casais', function (Blueprint $table) {
            if (Schema::hasColumn('casais', 'ativo')) {
                $table->dropColumn('ativo');
            }
        });

        Schema::dropIfExists('ecc_casal_equipe');
    }
};
