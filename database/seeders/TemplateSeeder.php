<?php

declare(strict_types = 1);

namespace Database\Seeders;

use App\Models\Template;
use App\Models\User;
use Illuminate\Database\Seeder;

class TemplateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $user = User::query()->first();

        Template::factory()->count(10)->create([
            'user_id' => $user->id,
        ]);
    }
}
