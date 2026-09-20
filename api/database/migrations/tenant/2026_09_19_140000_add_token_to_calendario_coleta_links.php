<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('calendario_coleta_links')) {
            return;
        }

        Schema::table('calendario_coleta_links', function (Blueprint $table) {
            if (! Schema::hasColumn('calendario_coleta_links', 'token')) {
                $table->string('token', 64)->nullable()->unique()->after('calendario_mensal_id');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('calendario_coleta_links')) {
            return;
        }

        Schema::table('calendario_coleta_links', function (Blueprint $table) {
            if (Schema::hasColumn('calendario_coleta_links', 'token')) {
                $table->dropUnique(['token']);
                $table->dropColumn('token');
            }
        });
    }
};
