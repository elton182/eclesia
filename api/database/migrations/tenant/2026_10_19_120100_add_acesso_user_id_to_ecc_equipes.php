<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ecc_equipes', function (Blueprint $table) {
            $table->foreignId('acesso_user_id')
                ->nullable()
                ->after('cor')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('ecc_equipes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('acesso_user_id');
        });
    }
};
