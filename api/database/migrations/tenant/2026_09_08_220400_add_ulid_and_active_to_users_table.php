<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->ulid('ulid')->nullable()->after('id');
            $table->boolean('is_active')->default(true)->after('password');
            $table->ulid('pessoa_id')->nullable()->after('is_active');
        });

        // Backfill ULID for existing rows (SQLite/MySQL)
        $users = \Illuminate\Support\Facades\DB::table('users')->whereNull('ulid')->get();
        foreach ($users as $user) {
            \Illuminate\Support\Facades\DB::table('users')
                ->where('id', $user->id)
                ->update(['ulid' => (string) \Illuminate\Support\Str::ulid()]);
        }

        Schema::table('users', function (Blueprint $table) {
            $table->unique('ulid');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['ulid']);
            $table->dropColumn(['ulid', 'is_active', 'pessoa_id']);
        });
    }
};
