<?php

namespace App\Http\Requests\Cashier;

use App\Enums\DiscountType;
use App\Enums\PaymentMethod;
use App\Services\Orders;
use App\Services\Pricing;
use App\Support\Format;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Cashier "Confirm payment": method, optional Senior/PWD discount (ID details recorded), and for
 * cash the amount received, which must cover the amount due after the discount.
 */
class TakePaymentRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'method' => ['required', Rule::enum(PaymentMethod::class)],
            'discount' => ['boolean'],
            'discount_type' => ['exclude_unless:discount,1', 'required', Rule::enum(DiscountType::class)],
            'id_no' => ['exclude_unless:discount,1', 'required', 'string', 'max:30'],
            'holder_name' => ['exclude_unless:discount,1', 'required', 'string', 'max:100'],
            'eligible_count' => ['exclude_unless:discount,1', 'required', 'integer', 'min:1'],
            'diner_count' => ['exclude_unless:discount,1', 'required', 'integer', 'gte:eligible_count'],
            'amount_tendered' => ['exclude_unless:method,CASH', 'required', 'numeric', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'id_no.required' => 'Enter the senior citizen or PWD ID number.',
            'holder_name.required' => 'Enter the name printed on the ID.',
            'diner_count.gte' => 'Diners sharing the order can’t be fewer than the eligible persons.',
            'amount_tendered.required' => 'Enter the amount received.',
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->isNotEmpty() || $this->input('method') !== 'CASH') {
                return;
            }

            $due = $this->amountDue();
            $tendered = (float) $this->input('amount_tendered');

            if ($tendered < $due) {
                $validator->errors()->add('amount_tendered', 'Amount received is short by '.Format::peso($due - $tendered).'.');
            }
        }];
    }

    /** Amount due for this order after the discount being applied, computed on the server. */
    public function amountDue(): float
    {
        $order = Orders::find($this->route('order'));
        $discount = $this->boolean('discount')
            ? Pricing::seniorDiscount($order['subtotal'], (int) $this->input('eligible_count'), (int) $this->input('diner_count'))
            : 0;

        return Pricing::total($order['subtotal'], $discount, $order['delivery_fee']);
    }
}
