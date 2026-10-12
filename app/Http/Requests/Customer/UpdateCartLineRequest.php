<?php

namespace App\Http\Requests\Customer;

use App\Services\CustomerCart;
use Illuminate\Foundation\Http\FormRequest;

/** Cart stepper (− / +): the line's new quantity; 0 removes the line. */
class UpdateCartLineRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'qty' => ['required', 'integer', 'min:0', 'max:'.CustomerCart::MAX_QTY],
        ];
    }
}
