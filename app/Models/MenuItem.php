<?php

namespace App\Models;

use App\Models\Concerns\FlushesCatalog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MenuItem extends Model
{
    use FlushesCatalog;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'price' => 'float',
            'tone' => 'array',
            'is_best_seller' => 'boolean',
            'is_new' => 'boolean',
            'sold_out_override' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function optionGroups(): HasMany
    {
        return $this->hasMany(OptionGroup::class)->orderBy('sort_order');
    }

    /** Ingredients used per serving (pivot: qty_per_serving). */
    public function ingredients(): BelongsToMany
    {
        return $this->belongsToMany(Ingredient::class, 'item_ingredients')->withPivot('qty_per_serving', 'position')->orderByPivot('position');
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }
}
