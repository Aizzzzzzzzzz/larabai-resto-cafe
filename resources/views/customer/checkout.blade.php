@extends('layouts.customer', ['view' => 'checkout'])

@section('title', 'Checkout · Brew & Bite Café')

@php
    $typeLabels = [
        'dine-in' => ["Dine-in · Table {$qr['table']}", 'We bring your order to your table.'],
        'take-out' => ['Take-out', 'Pick up at the counter when it’s ready.'],
        'delivery' => ['Delivery', config('cafe.delivery.area').' · '.peso($deliveryFee, false).' fee'],
    ];
    $type = old('order_type', $orderTypes[0]);
    $method = old('payment_method', 'GCASH');
    $isDelivery = $type === 'delivery';
    $total = $subtotal + ($isDelivery ? $deliveryFee : 0);
@endphp

@section('content')
    <form class="app" method="POST" action="{{ route('customer.checkout.store') }}" novalidate
          data-checkout data-subtotal="{{ $subtotal }}" data-delivery-fee="{{ $deliveryFee }}">
        @csrf
        <x-customer.bar title="Checkout" :back="route('customer.cart.index')" back-label="Back to your order" />

        <div class="pad">
            @if ($notes)
                <p class="msg ok">Your order was updated: {{ implode(' ', $notes) }}</p>
            @endif
            @if ($errors->any())
                <p class="msg bad" role="alert">{{ $errors->first() }}</p>
            @endif

            <section class="card">
                <h2>How do you want it?</h2>
                <div class="choices">
                    @foreach ($orderTypes as $t)
                        <label class="choice"><input type="radio" name="order_type" value="{{ $t }}" @checked($t === $type)>
                            <span><b>{{ $typeLabels[$t][0] }}</b><small>{{ $typeLabels[$t][1] }}</small></span><span></span></label>
                    @endforeach
                </div>
            </section>

            @if (in_array('delivery', $orderTypes, true))
                <section class="card" data-show-for="delivery" @unless ($isDelivery) hidden @endunless>
                    <h2>Delivery address</h2>
                    <div>
                        <div class="field"><label for="address">Address</label><textarea id="address" name="delivery_address" rows="2" placeholder="House no., street, barangay">{{ old('delivery_address') }}</textarea></div>
                        <div class="field" style="margin-top:10px"><label for="landmark">Landmark <span class="muted" style="font-weight:400">(optional)</span></label><input id="landmark" name="delivery_landmark" value="{{ old('delivery_landmark') }}" placeholder="e.g. beside the barangay hall"></div>
                    </div>
                </section>
            @endif

            <section class="card">
                <h2>Payment method</h2>
                <div class="choices">
                    <label class="choice"><input type="radio" name="payment_method" value="GCASH" @checked($method === 'GCASH')>
                        <span><b>GCash</b><small>Pay now from your phone</small></span><span class="badge gcash">GCash</span></label>
                    <label class="choice"><input type="radio" name="payment_method" value="MAYA" @checked($method === 'MAYA')>
                        <span><b>Maya</b><small>Pay now from your phone</small></span><span class="badge maya">Maya</span></label>
                    <label class="choice"><input type="radio" name="payment_method" value="CASH" @checked($method === 'CASH')>
                        <span data-show-for="delivery" @unless ($isDelivery) hidden @endunless><b>Cash on delivery</b><small>Pay the rider when your order arrives</small></span>
                        <span data-hide-for="delivery" @if ($isDelivery) hidden @endif><b>Cash at the counter</b><small>Show your order number to the cashier</small></span>
                        <span class="badge cash">Cash</span></label>
                </div>
            </section>

            <section class="card">
                <div class="card-head"><h2>Order summary</h2><a class="link-btn" href="{{ route('customer.cart.index') }}">Edit</a></div>
                <div style="display:grid;gap:8px">
                    <x-order-lines :lines="$lines" />
                </div>
                <div class="r-sep"></div>
                <div class="sum">
                    <div><span>Subtotal</span><span>{{ peso($subtotal) }}</span></div>
                    <div data-show-for="delivery" @unless ($isDelivery) hidden @endunless><span>Delivery fee</span><span>{{ peso($deliveryFee) }}</span></div>
                    <div class="total"><span>Total</span><span data-checkout-total>{{ peso($total) }}</span></div>
                    <div class="fine"><span>Prices include 12% VAT. Senior citizen and PWD discounts are applied by the cashier.</span></div>
                </div>
            </section>
        </div>

        <div class="action-bar">
            <button class="btn btn-primary" type="submit" data-checkout-submit>Place order · {{ peso($total) }}</button>
            <small data-checkout-hint></small>
        </div>
    </form>
@endsection
