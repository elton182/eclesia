<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('igrejas', function (Blueprint $table) {
            $table->string('tipo', 32)->default('paroquia')->after('nome');
            $table->text('endereco')->nullable()->after('tipo');
            $table->text('bairro')->nullable()->after('endereco');
            $table->text('cidade')->nullable()->after('bairro');
            $table->string('uf', 2)->nullable()->after('cidade');
            $table->text('cep')->nullable()->after('uf');
            $table->text('telefone')->nullable()->after('cep');
            $table->text('email')->nullable()->after('telefone');
        });
    }

    public function down(): void
    {
        Schema::table('igrejas', function (Blueprint $table) {
            $table->dropColumn([
                'tipo',
                'endereco',
                'bairro',
                'cidade',
                'uf',
                'cep',
                'telefone',
                'email',
            ]);
        });
    }
};
