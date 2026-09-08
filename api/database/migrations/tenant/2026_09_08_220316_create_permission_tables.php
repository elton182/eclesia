<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Spatie permission tables no banco do tenant.
 * teams = igreja_id (ULID string, nullable para admin-tenant).
 *
 * MySQL faz commit implícito em DDL: se a migration falhar no meio,
 * as tabelas podem existir sem registro em `migrations`. Por isso
 * o up() remove restos antes de recriar.
 */
return new class extends Migration
{
    public function up(): void
    {
        $teams = config('permission.teams');
        $tableNames = config('permission.table_names');
        $columnNames = config('permission.column_names');
        $pivotRole = $columnNames['role_pivot_key'] ?? 'role_id';
        $pivotPermission = $columnNames['permission_pivot_key'] ?? 'permission_id';
        $teamKey = $columnNames['team_foreign_key'] ?? 'igreja_id';

        throw_if(empty($tableNames), 'Error: config/permission.php not loaded.');
        throw_if($teams && empty($teamKey), 'Error: team_foreign_key not configured.');

        // Remove restos de execução parcial antes de criar.
        $this->dropPermissionTables($tableNames);

        Schema::create($tableNames['permissions'], static function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();
            $table->unique(['name', 'guard_name']);
        });

        Schema::create($tableNames['roles'], static function (Blueprint $table) use ($teams, $teamKey) {
            $table->id();
            if ($teams || config('permission.testing')) {
                $table->string($teamKey, 26)->nullable();
                $table->index($teamKey, 'roles_team_foreign_key_index');
            }
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();
            if ($teams || config('permission.testing')) {
                $table->unique([$teamKey, 'name', 'guard_name']);
            } else {
                $table->unique(['name', 'guard_name']);
            }
        });

        Schema::create($tableNames['model_has_permissions'], static function (Blueprint $table) use ($tableNames, $columnNames, $pivotPermission, $teams, $teamKey) {
            $table->unsignedBigInteger($pivotPermission);
            $table->string('model_type');
            $table->unsignedBigInteger($columnNames['model_morph_key']);
            $table->index([$columnNames['model_morph_key'], 'model_type'], 'model_has_permissions_model_id_model_type_index');

            $table->foreign($pivotPermission)
                ->references('id')
                ->on($tableNames['permissions'])
                ->cascadeOnDelete();

            if ($teams) {
                $table->string($teamKey, 26)->nullable();
                $table->index($teamKey, 'model_has_permissions_team_foreign_key_index');
                $table->unique(
                    [$teamKey, $pivotPermission, $columnNames['model_morph_key'], 'model_type'],
                    'model_has_permissions_permission_model_type_unique'
                );
            } else {
                $table->primary(
                    [$pivotPermission, $columnNames['model_morph_key'], 'model_type'],
                    'model_has_permissions_permission_model_type_primary'
                );
            }
        });

        Schema::create($tableNames['model_has_roles'], static function (Blueprint $table) use ($tableNames, $columnNames, $pivotRole, $teams, $teamKey) {
            $table->unsignedBigInteger($pivotRole);
            $table->string('model_type');
            $table->unsignedBigInteger($columnNames['model_morph_key']);
            $table->index([$columnNames['model_morph_key'], 'model_type'], 'model_has_roles_model_id_model_type_index');

            $table->foreign($pivotRole)
                ->references('id')
                ->on($tableNames['roles'])
                ->cascadeOnDelete();

            if ($teams) {
                $table->string($teamKey, 26)->nullable();
                $table->index($teamKey, 'model_has_roles_team_foreign_key_index');
                $table->unique(
                    [$teamKey, $pivotRole, $columnNames['model_morph_key'], 'model_type'],
                    'model_has_roles_role_model_type_unique'
                );
            } else {
                $table->primary(
                    [$pivotRole, $columnNames['model_morph_key'], 'model_type'],
                    'model_has_roles_role_model_type_primary'
                );
            }
        });

        Schema::create($tableNames['role_has_permissions'], static function (Blueprint $table) use ($tableNames, $pivotRole, $pivotPermission) {
            $table->unsignedBigInteger($pivotPermission);
            $table->unsignedBigInteger($pivotRole);

            $table->foreign($pivotPermission)
                ->references('id')
                ->on($tableNames['permissions'])
                ->cascadeOnDelete();

            $table->foreign($pivotRole)
                ->references('id')
                ->on($tableNames['roles'])
                ->cascadeOnDelete();

            $table->primary([$pivotPermission, $pivotRole], 'role_has_permissions_permission_id_role_id_primary');
        });

        try {
            app('cache')
                ->store(config('permission.cache.store') != 'default' ? config('permission.cache.store') : null)
                ->forget(config('permission.cache.key'));
        } catch (\Throwable) {
            // Durante o provisionamento o store database pode ainda não estar pronto.
        }
    }

    public function down(): void
    {
        $this->dropPermissionTables(config('permission.table_names'));
    }

    /**
     * @param  array<string, string>  $tableNames
     */
    private function dropPermissionTables(array $tableNames): void
    {
        Schema::dropIfExists($tableNames['role_has_permissions'] ?? 'role_has_permissions');
        Schema::dropIfExists($tableNames['model_has_roles'] ?? 'model_has_roles');
        Schema::dropIfExists($tableNames['model_has_permissions'] ?? 'model_has_permissions');
        Schema::dropIfExists($tableNames['roles'] ?? 'roles');
        Schema::dropIfExists($tableNames['permissions'] ?? 'permissions');
    }
};
