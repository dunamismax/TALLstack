<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::query()->firstOrCreate(
            ['email' => 'test@example.com'],
            [
                'name' => 'Test User',
                'password' => 'password',
            ],
        );

        $targetUserCount = 5;
        $additionalUsers = max(0, $targetUserCount - User::query()->count());

        if ($additionalUsers > 0) {
            User::factory()->count($additionalUsers)->create();
        }

        $this->call(AccessControlSeeder::class);
    }
}
