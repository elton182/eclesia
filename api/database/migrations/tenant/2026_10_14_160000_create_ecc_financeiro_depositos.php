<?php

declare(strict_types=1);

use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('ecc_financeiro_lancamentos', 'status')) {
            Schema::table('ecc_financeiro_lancamentos', function (Blueprint $table) {
                $table->string('status', 16)->default('confirmado')->after('abertura');
                $table->index(['igreja_id', 'status']);
            });
        }

        if (! Schema::hasTable('ecc_financeiro_depositos')) {
            Schema::create('ecc_financeiro_depositos', function (Blueprint $table) {
                $table->ulid('id')->primary();
                $table->foreignUlid('igreja_id')->constrained('igrejas')->cascadeOnDelete();
                $table->foreignUlid('ecc_equipe_id')->constrained('ecc_equipes')->restrictOnDelete();
                $table->date('competencia');
                $table->decimal('valor', 12, 2);
                $table->foreignUlid('ecc_financeiro_conta_id')->constrained('ecc_financeiro_contas')->restrictOnDelete();
                $table->foreignUlid('ecc_financeiro_lancamento_id')->constrained('ecc_financeiro_lancamentos')->cascadeOnDelete();
                $table->string('comprovante_path');
                $table->string('status', 16); // provisionado | confirmado | rejeitado
                $table->text('motivo_rejeicao')->nullable();
                $table->foreignUlid('submetido_por_user_id')->constrained('users')->restrictOnDelete();
                $table->foreignUlid('revisado_por_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('revisado_em')->nullable();
                $table->timestamps();

                $table->index(['igreja_id', 'status']);
                $table->index(['igreja_id', 'ecc_equipe_id', 'competencia']);
            });
        }

        (new RolesAndPermissionsSeeder)->run();
    }

    public function down(): void
    {
        Schema::dropIfExists('ecc_financeiro_depositos');

        if (Schema::hasColumn('ecc_financeiro_lancamentos', 'status')) {
            Schema::table('ecc_financeiro_lancamentos', function (Blueprint $table) {
                $table->dropIndex(['igreja_id', 'status']);
                $table->dropColumn('status');
            });
        }
    }
};
