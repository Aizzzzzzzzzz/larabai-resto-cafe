<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * `php artisan db:seed` — the café's starting data. The menu, stock and tables are always seeded; in demo mode
 * (CAFE_DEMO_MODE) today's sample orders and 30 days of sales history come with them, so every screen has
 * something to show. A real café turns demo mode off and gets only one admin account.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            UserSeeder::class,
            DiningTableSeeder::class,
            IngredientSeeder::class,
            MenuSeeder::class,
        ]);

        if (config('cafe.demo_mode')) {
            $this->call([
                SampleOrdersSeeder::class,
                SampleHistorySeeder::class,
            ]);
        }
    }
}
