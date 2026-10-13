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

        // A real café's stock history starts with a count. In demo mode SampleOrdersSeeder writes a fuller history
        // that ends at the same amounts.
        if (! config('cafe.demo_mode')) {
            foreach (Ingredient::all() as $ingredient) {
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
