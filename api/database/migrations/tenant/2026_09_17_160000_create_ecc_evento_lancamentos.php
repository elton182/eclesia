<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ecc_evento_lancamentos', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('ecc_evento_id')->constrained('ecc_eventos')->cascadeOnDelete();
            $table->string('tipo', 16); // entrada | saida
            $table->decimal('valor', 12, 2);
            $table->string('descricao')->nullable();
            $table->foreignUlid('casal_id')->nullable()->constrained('casais')->nullOnDelete();
            $table->foreignUlid('ecc_equipe_id')->nullable()->constrained('ecc_equipes')->nullOnDelete();
            $table->foreignUlid('ecc_item_compra_id')->nullable()->constrained('ecc_itens_compra')->nullOnDelete();
            $table->timestamps();

            $table->index(['ecc_evento_id', 'tipo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ecc_evento_lancamentos');
    }
};
