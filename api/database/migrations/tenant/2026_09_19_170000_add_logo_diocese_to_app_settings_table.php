<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('app_settings')) {
            return;
        }

        Schema::table('app_settings', function (Blueprint $table): void {
            if (! Schema::hasColumn('app_settings', 'logo_diocese_path')) {
                $table->string('logo_diocese_path')->nullable()->after('logo_path');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('app_settings') || ! Schema::hasColumn('app_settings', 'logo_diocese_path')) {
            return;
        }

        Schema::table('app_settings', function (Blueprint $table): void {
            $table->dropColumn('logo_diocese_path');
        });
    }
};
