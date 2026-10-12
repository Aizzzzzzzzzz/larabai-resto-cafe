@extends('layouts.staff', ['module' => 'cashier', 'view' => 'payment', 'active' => 'orders'])

@php
    use App\Enums\PaymentMethod;
    use App\Support\CashierActions;
    use App\Support\Format;

    $where = Format::orderType($order['order_type'], $order['table_no']);
    $method = old('method', $order['payment_method'] !== 'CASH' ? $order['payment_method'] : 'CASH');
    $discountOn = (bool) old('discount');
@endphp

@section('title', 'Take Payment · Brew & Bite Café')
@section('heading', CashierActions::isCodCollection($order) ? 'Collect COD payment' : 'Take payment')
@section('sub', $order['order_no'].' · '.$where.' · '.($order['source'] === 'pos' ? 'Counter order' : 'QR order'))
@section('actions')
    <a class="btn btn-ghost btn-sm" href="{{ route('cashier.orders.index') }}">← Back to orders</a>
@endsection

@section('content')
    @if (in_array($order['status'], ['cancelled', 'voided'], true))
        <div class="panel state">
            <h2>This order was {{ $order['status'] }}</h2>
            <p class="muted">{{ $order['cancel_reason'] }}</p>
            <a class="btn btn-primary" href="{{ route('cashier.orders.index') }}">Back to orders</a>
        </div>
    @elseif ($order['paid_at'])
        <div class="panel state">
            <h2>Already paid</h2>
            <p class="muted">{{ $order['order_no'] }} was paid by {{ PaymentMethod::from($order['payment']['method'])->label() }} ({{ $order['payment']['reference_no'] }}).</p>
            <a class="btn btn-primary" href="{{ route('cashier.receipt', $order['id']) }}">View receipt</a>
        </div>
    @else
        <div class="pay">
            <section class="panel panel-pad">
                <div class="meta">
                    <span>Order no.</span><span class="mono">{{ $order['order_no'] }}</span>
                    <span>Placed</span><span>{{ Format::dateTime($order['created_at']) }} · {{ Format::ago($order['created_at']) }}</span>
                    <span>Type</span><span>{{ $where }}</span>
                    @if ($order['delivery_address'])
                        <span>Deliver to</span><span>{{ $order['delivery_address'] }}</span>
                    @endif
                </div>
                <div><x-order-lines :lines="$order['items']" /></div>
            </section>

            {{-- The amounts shown here are a preview; the server computes the discount and checks the cash again. --}}
            <form class="panel panel-pad" method="POST" action="{{ route('cashier.payments.store', $order['id']) }}" novalidate
                  data-payment data-subtotal="{{ $order['subtotal'] }}" data-delivery-fee="{{ $order['delivery_fee'] }}"
                  data-vat="{{ config('cafe.senior_pwd.vat') }}" data-percent="{{ config('cafe.senior_pwd.percent') }}">
                @csrf
                <label class="switch"><input type="hidden" name="discount" value="0"><input type="checkbox" name="discount" value="1" @checked($discountOn)> Senior citizen / PWD discount</label>
                <div data-discount-fields @unless ($discountOn) hidden @endunless style="display:grid;gap:10px">
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">
                        <label class="lbl">Type
                            <select class="input" name="discount_type">
                                @foreach ($discountTypes as $type)
                                    <option value="{{ $type->value }}" @selected(old('discount_type') === $type->value)>{{ $type->label() }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="lbl">ID number<input class="input" name="id_no" value="{{ old('id_no') }}" placeholder="e.g. OSCA-TC-04512"></label>
                        <label class="lbl" style="grid-column:span 2">Name on ID<input class="input" name="holder_name" value="{{ old('holder_name') }}"></label>
                        <label class="lbl">Eligible persons<input class="input" name="eligible_count" type="number" min="1" value="{{ old('eligible_count', 1) }}"></label>
                        <label class="lbl">Total diners sharing<input class="input" name="diner_count" type="number" min="1" value="{{ old('diner_count', 1) }}"></label>
                    </div>
                    <p class="note" data-discount-note></p>
                </div>

                <div class="r-sep" style="border-top:1px dashed var(--line)"></div>
                <div class="sum">
                    <div><span>Subtotal</span><span>{{ peso($order['subtotal']) }}</span></div>
                    <div class="minus" data-discount-row hidden><span data-discount-label></span><span data-discount-amount></span></div>
                    @if ($order['delivery_fee'])
                        <div><span>Delivery fee</span><span>{{ peso($order['delivery_fee']) }}</span></div>
                    @endif
                    <div class="big"><span>Amount due</span><span data-amount-due>{{ peso($order['total']) }}</span></div>
                </div>

                <div class="r-sep" style="border-top:1px dashed var(--line)"></div>
                <div class="methods" role="radiogroup" aria-label="Payment method">
                    @foreach (PaymentMethod::cases() as $m)
                        <label><input type="radio" name="method" value="{{ $m->value }}" @checked($method === $m->value)> {{ $m->label() }}</label>
                    @endforeach
                </div>

                <div data-cash-box @unless ($method === 'CASH') hidden @endunless style="display:grid;gap:10px">
                    <label class="lbl">Amount received<input class="input" name="amount_tendered" type="number" min="0" step="0.01" inputmode="decimal" value="{{ old('amount_tendered') }}" style="font-size:1.2rem;font-family:var(--mono)"></label>
                    <div class="quick" data-quick>
                        @foreach ($quickCash as $i => $amount)
                            <button type="button" class="btn btn-ghost btn-sm" data-cash="{{ $amount }}">{{ $i === 0 ? 'Exact ' : '' }}{{ peso($amount, $i === 0) }}</button>
                        @endforeach
                    </div>
                    <div class="change"><span>Change</span><strong data-change>{{ peso(0) }}</strong></div>
                </div>
                <p class="note" data-wallet-box @if ($method === 'CASH') hidden @endif>Simulated e-wallet payment. The customer scans the café’s QR at the counter; tapping Confirm saves the payment record with a reference number. No real money moves.</p>

                @if ($errors->any())
                    <p class="err" role="alert">{{ $errors->first() }}</p>
                @endif
                <button class="btn btn-primary confirm" type="submit" data-confirm-pay>Confirm payment · {{ peso($order['total']) }}</button>
            </form>
        </div>
    @endif
@endsection
