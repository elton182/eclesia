<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('planejamento_pastoral_tokens', 'token_encrypted')) {
            return;
        }

        Schema::table('planejamento_pastoral_tokens', function (Blueprint $table) {
            $table->text('token_encrypted')->nullable()->after('token_preview');
        });
    }

    public function down(): void
    {
        Schema::table('planejamento_pastoral_tokens', function (Blueprint $table) {
            $table->dropColumn('token_encrypted');
        });
    }
};
