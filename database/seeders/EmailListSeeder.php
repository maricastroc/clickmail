<?php

declare(strict_types = 1);

namespace Database\Seeders;

use App\Models\EmailList;
use App\Models\Subscriber;
use App\Models\User;
use Illuminate\Database\Seeder;

class EmailListSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $user = User::query()->first();

        EmailList::factory()->count(50)->create([
            'user_id' => $user->id,
        ])
            ->each(function (EmailList $list) {
                Subscriber::factory()
                    ->count(rand(50, 200))
                    ->create([
                        'email_list_id' => $list->id,
                    ]);
            });
    }
}
