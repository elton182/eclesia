<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * SuperAdmin (e futuros users) usam ULID/UUID — sessions.user_id não pode ser bigint.
     */
    public function up(): void
    {
        if (! Schema::hasTable('sessions')) {
            return;
        }

        Schema::table('sessions', function (Blueprint $table) {
            $table->dropIndex(['user_id']);
        });

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE sessions MODIFY user_id VARCHAR(36) NULL');
        } elseif ($driver === 'pgsql') {
            DB::statement('ALTER TABLE sessions ALTER COLUMN user_id TYPE VARCHAR(36) USING user_id::text');
        } else {
            // sqlite / outros: recria coluna via doctrine-less approach
            Schema::table('sessions', function (Blueprint $table) {
                $table->string('user_id_tmp', 36)->nullable();
            });
            DB::table('sessions')->update([
                'user_id_tmp' => DB::raw('CAST(user_id AS TEXT)'),
            ]);
            Schema::table('sessions', function (Blueprint $table) {
                $table->dropColumn('user_id');
            });
            Schema::table('sessions', function (Blueprint $table) {
                $table->renameColumn('user_id_tmp', 'user_id');
            });
        }

        Schema::table('sessions', function (Blueprint $table) {
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('sessions')) {
            return;
        }

        Schema::table('sessions', function (Blueprint $table) {
            $table->dropIndex(['user_id']);
        });

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE sessions MODIFY user_id BIGINT UNSIGNED NULL');
        } elseif ($driver === 'pgsql') {
            DB::statement('ALTER TABLE sessions ALTER COLUMN user_id TYPE BIGINT USING NULL');
        } else {
            Schema::table('sessions', function (Blueprint $table) {
                $table->unsignedBigInteger('user_id_tmp')->nullable();
            });
            Schema::table('sessions', function (Blueprint $table) {
                $table->dropColumn('user_id');
            });
            Schema::table('sessions', function (Blueprint $table) {
                $table->renameColumn('user_id_tmp', 'user_id');
            });
        }

        Schema::table('sessions', function (Blueprint $table) {
            $table->index('user_id');
        });
    }
};
