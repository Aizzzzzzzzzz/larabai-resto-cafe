<?php

namespace App\Models;

use App\Enums\DiscountType;
use Illuminate\Database\Eloquent\Model;

/** Senior/PWD discount. The ID number and holder name are personal data, so they are stored encrypted. */
class Discount extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'discount_type' => DiscountType::class,
            'id_no' => 'encrypted',
            'holder_name' => 'encrypted',
            'amount' => 'float',
        ];
    }
}
