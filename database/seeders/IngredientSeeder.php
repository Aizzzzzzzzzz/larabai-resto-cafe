<?php

namespace Database\Seeders;

use App\Models\Ingredient;
use App\Models\StockMovement;
use Database\Seeders\Data\SampleData;
use Illuminate\Database\Seeder;

class IngredientSeeder extends Seeder
{
    public function run(): void
    {
        foreach (SampleData::ingredients() as $i) {
            Ingredient::create($i);
        }

        // Without the sample orders, each ingredient's history starts with a count. With them, SampleOrdersSeeder
        // writes a fuller history that ends at the same amounts.
        if (! config('cafe.sample_orders')) {
            foreach (Ingredient::where('stock_qty', '>', 0)->get() as $ingredient) {
                StockMovement::create([
                    'ingredient_id' => $ingredient->id,
                    'type' => 'ADJUST',
                    'qty' => $ingredient->stock_qty,
                    'qty_before' => 0,
                    'qty_after' => $ingredient->stock_qty,
                    'cost' => round($ingredient->stock_qty * $ingredient->unit_cost, 2),
                    'note' => 'Opening stock',
                    'moved_at' => now(),
                ]);
            }
        }
    }
}
