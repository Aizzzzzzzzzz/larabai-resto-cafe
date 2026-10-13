<?php

namespace Database\Seeders;

use App\Models\DiningTable;
use Database\Seeders\Data\SampleData;
use Illuminate\Database\Seeder;

class DiningTableSeeder extends Seeder
{
    public function run(): void
    {
        foreach (SampleData::tables() as $table) {
            DiningTable::create($table);
        }
    }
}
