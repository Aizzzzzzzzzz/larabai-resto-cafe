<?php

namespace App\Http\Requests\Kitchen;

use App\Support\KitchenActions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** A ticket button on the kitchen display: start, back, ready, done or recall. */
class MoveTicketRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'move' => ['required', Rule::in(KitchenActions::MOVES)],
        ];
    }
}
