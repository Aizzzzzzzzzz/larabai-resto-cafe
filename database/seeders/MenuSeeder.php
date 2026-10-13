<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Services\MenuBuilder;
use Database\Seeders\Data\SampleData;
use Illuminate\Database\Seeder;

class MenuSeeder extends Seeder
{
    public function run(MenuBuilder $builder): void
    {
        foreach (SampleData::categories() as $c) {
            Category::create(['code' => $c['code'], 'name' => $c['name'], 'icon' => $c['icon'], 'is_drink' => $c['is_drink'], 'sort_order' => $c['sort_order']]);
        }

        $recipes = SampleData::recipes();

        foreach (SampleData::items() as $item) {
            $builder->create([
                'code' => $item['code'],
                'category' => $item['category'],
                'name' => $item['name'],
                'description' => $item['description'],
                'price' => $item['price'],
                'tone' => $item['tone'],
                'is_best_seller' => $item['is_best_seller'],
                'is_new' => $item['is_new'],
            ], $item['options'], $recipes[$item['code']]);
        }
    }
}
