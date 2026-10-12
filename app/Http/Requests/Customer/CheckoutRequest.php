<?php

namespace App\Http\Requests\Customer;

use App\Enums\PaymentMethod;
use App\Services\CustomerCart;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Checkout: how the order is served and how it's paid. A table QR allows dine-in or take-out;
 * the counter QR allows take-out or delivery. Delivery needs an address — nothing else
 * personal is asked (document.md objective 7).
 */
class CheckoutRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'order_type' => ['required', Rule::in(self::orderTypesFor(session('qr')))],
            'payment_method' => ['required', Rule::enum(PaymentMethod::class)],
            'delivery_address' => ['nullable', 'required_if:order_type,delivery', 'string', 'max:255'],
            'delivery_landmark' => ['nullable', 'string', 'max:150'],
        ];
    }

    public function messages(): array
    {
        return [
            'delivery_address.required_if' => 'Enter your delivery address so the rider can find you.',
            'order_type.in' => 'Choose how you want your order.',
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            $cart = app(CustomerCart::class);

            if ($cart->isEmpty()) {
                $validator->errors()->add('cart', 'Your order is empty.');

                return;
            }

            $gone = array_column(array_filter($cart->lines(), fn ($l) => $l['sold_out']), 'name');
            if ($gone) {
                $validator->errors()->add('cart', 'Sorry, '.implode(', ', $gone).' just sold out. Go back to your order and remove it.');
            }
        }];
    }

    /** @return string[] */
    public static function orderTypesFor(?array $qr): array
    {
        return ($qr['type'] ?? null) === 'dine-in' ? ['dine-in', 'take-out'] : ['take-out', 'delivery'];
    }
}
