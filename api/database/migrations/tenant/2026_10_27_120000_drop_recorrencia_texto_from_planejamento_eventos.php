<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('planejamento_eventos')) {
            return;
        }

        if (Schema::hasColumn('planejamento_eventos', 'recorrencia_texto')) {
            Schema::table('planejamento_eventos', function (Blueprint $table) {
                $table->dropColumn('recorrencia_texto');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('planejamento_eventos')) {
            return;
        }

        if (! Schema::hasColumn('planejamento_eventos', 'recorrencia_texto')) {
            Schema::table('planejamento_eventos', function (Blueprint $table) {
                $table->string('recorrencia_texto')->nullable()->after('participantes_media');
            });
        }
    }
};
