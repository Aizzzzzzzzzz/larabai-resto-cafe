<?php

namespace App\Models;

use App\Models\Concerns\FlushesCatalog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OptionGroup extends Model
{
    use FlushesCatalog;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_required' => 'boolean'];
    }

    public function menuItem(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class);
    }

    public function choices(): HasMany
    {
        return $this->hasMany(OptionChoice::class)->orderBy('sort_order');
    }
}
