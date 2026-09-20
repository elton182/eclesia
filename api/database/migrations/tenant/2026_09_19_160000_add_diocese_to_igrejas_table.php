<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('igrejas')) {
            return;
        }

        Schema::table('igrejas', function (Blueprint $table) {
            if (! Schema::hasColumn('igrejas', 'diocese')) {
                $table->string('diocese')->nullable()->after('uf');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('igrejas') || ! Schema::hasColumn('igrejas', 'diocese')) {
            return;
        }

        Schema::table('igrejas', function (Blueprint $table) {
            $table->dropColumn('diocese');
        });
    }
};
