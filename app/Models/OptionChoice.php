<?php

namespace App\Models;

use App\Models\Concerns\FlushesCatalog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class OptionChoice extends Model
{
    use FlushesCatalog;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['extra_price' => 'float', 'is_default' => 'boolean'];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(OptionGroup::class, 'option_group_id');
    }

    /** What picking this choice adds to the recipe, or swaps in for another ingredient. */
    public function ingredients(): BelongsToMany
    {
        return $this->belongsToMany(Ingredient::class, 'choice_ingredients')->withPivot('qty', 'action', 'replaces_id');
    }
}
