<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ecc_equipe_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUlid('ecc_equipe_id')->constrained('ecc_equipes')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'ecc_equipe_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ecc_equipe_user');
    }
};
