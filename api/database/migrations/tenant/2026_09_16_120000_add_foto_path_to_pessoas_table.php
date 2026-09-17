<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pessoas', function (Blueprint $table) {
            if (! Schema::hasColumn('pessoas', 'foto_path')) {
                $table->string('foto_path')->nullable()->after('sexo');
            }
        });
    }

    public function down(): void
    {
        Schema::table('pessoas', function (Blueprint $table) {
            if (Schema::hasColumn('pessoas', 'foto_path')) {
                $table->dropColumn('foto_path');
            }
        });
    }
};
