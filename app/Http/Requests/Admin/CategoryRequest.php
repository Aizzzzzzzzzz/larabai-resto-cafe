<?php

namespace App\Http\Requests\Admin;

use App\Support\DemoData;
use Illuminate\Foundation\Http\FormRequest;

/** Add or rename a menu category; names are unique (case-insensitive). */
class CategoryRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        abort_if($this->route('category') && ! collect(DemoData::categories())->contains('code', $this->route('category')), 404);
    }

    public function rules(): array
    {
        $editing = $this->route('category');

        return [
            'name' => ['required', 'string', 'max:50', function ($attribute, $value, $fail) use ($editing) {
                $taken = collect(DemoData::categories())
                    ->contains(fn ($c) => mb_strtolower($c['name']) === mb_strtolower(trim($value)) && $c['code'] !== $editing);
                if ($taken) {
                    $fail("{$value} already exists.");
                }
            }],
        ];
    }

    public function messages(): array
    {
        return ['name.required' => 'Enter a category name.'];
    }
}
