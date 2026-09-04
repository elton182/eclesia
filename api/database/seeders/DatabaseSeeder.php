<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\SuperAdmin;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        SuperAdmin::query()->updateOrCreate(
            ['email' => 'admin@eclesia.local'],
            [
                'name' => 'Super Admin',
                'password' => 'password',
            ],
        );
    }
}
