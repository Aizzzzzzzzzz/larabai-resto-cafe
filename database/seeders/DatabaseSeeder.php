<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * `php artisan db:seed` — the café's starting data. The menu, stock and tables are always seeded. The five demo
 * accounts come with demo mode (CAFE_DEMO_MODE; a real café gets one admin instead). Today's sample orders and
 * 30 days of sales come with CAFE_SAMPLE_ORDERS; turn that off for a fresh start with no orders.
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

        if (config('cafe.sample_orders')) {
            $this->call([
                SampleOrdersSeeder::class,
                SampleHistorySeeder::class,
            ]);
        }
    }
}
