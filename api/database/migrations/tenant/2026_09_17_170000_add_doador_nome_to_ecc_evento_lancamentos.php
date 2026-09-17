<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ecc_evento_lancamentos', function (Blueprint $table) {
            $table->text('doador_nome')->nullable()->after('ecc_equipe_id');
        });
    }

    public function down(): void
    {
        Schema::table('ecc_evento_lancamentos', function (Blueprint $table) {
            $table->dropColumn('doador_nome');
        });
    }
};
