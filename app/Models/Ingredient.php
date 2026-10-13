<?php

namespace App\Models;

use App\Enums\Unit;
use App\Models\Concerns\FlushesCatalog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ingredient extends Model
{
    use FlushesCatalog;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'unit' => Unit::class,
            'stock_qty' => 'float',
            'reorder_level' => 'float',
            'restock_qty' => 'float',
            'unit_cost' => 'float',
        ];
    }

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function menuItems(): BelongsToMany
    {
        return $this->belongsToMany(MenuItem::class, 'item_ingredients')->withPivot('qty_per_serving');
    }
}
