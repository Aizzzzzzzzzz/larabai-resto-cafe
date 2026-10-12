@extends('layouts.customer', ['view' => 'success'])

@section('title', 'Order placed · Brew & Bite Café')

@php
    use App\Support\Format;

    $paid = (bool) $order['paid_at'];
    $payment = $order['payment'];
    $vatable = round($order['total'] / 1.12, 2);
@endphp

@section('content')
    <div class="app" style="padding-bottom:150px">
        <x-customer.bar title="Receipt" :back="route('customer.menu')" back-label="Back to menu" />

        <div class="done-hero">
            @if (in_array($order['status'], ['cancelled', 'voided'], true))
                <h1>This order was {{ $order['status'] }}</h1>
                <p class="muted">{{ $order['cancel_reason'] }}</p>
            @elseif ($paid)
                <div class="done-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m5 12 5 5 9-10"/></svg></div>
                <h1>Payment successful</h1>
                <p class="muted">We’ve sent your order to the kitchen.@if ($order['order_type'] === 'dine-in') We’ll bring it to Table {{ $order['table_no'] }}.@endif</p>
            @else
                <div class="done-icon wait"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="12" r="8"/><path d="M12 8v4l3 2"/></svg></div>
                <h1>Order placed. Please pay {{ peso($order['total']) }}</h1>
                <p class="muted">
                    @if ($order['order_type'] === 'delivery')
                        Pay the rider in cash when your order arrives.
                    @else
                        Go to the counter and show this order number to the cashier. We start preparing your order once it’s paid.
                    @endif
                </p>
            @endif
            <span class="order-no">{{ $order['order_no'] }}</span>
        </div>

        <div class="pad">
            <article class="receipt">
                <div class="r-head">
                    <b>{{ config('cafe.name') }}</b>
                    <small>{{ config('cafe.address') }}</small>
                    <small>{{ $paid ? 'Order receipt' : 'Order slip · not yet paid' }} · For demo only, not an official receipt</small>
                </div>
                <div class="r-sep"></div>
                <div class="r-meta">
                    <span>Order no.</span><span>{{ $order['order_no'] }}</span>
                    <span>Date</span><span>{{ Format::dateTime($order['created_at']) }}</span>
                    <span>Order type</span><span>{{ Format::orderType($order['order_type'], $order['table_no']) }}</span>
                    @if ($order['delivery_address'])
                        <span>Deliver to</span><span>{{ $order['delivery_address'] }}</span>
                    @endif
                </div>
                <div class="r-sep"></div>
                <div style="display:grid;gap:8px">
                    <x-order-lines :lines="$order['items']" />
                </div>
                <div class="r-sep"></div>
                <div class="sum">
                    <div><span>Subtotal</span><span>{{ peso($order['subtotal']) }}</span></div>
                    @if ($order['discount'])
                        <div class="minus"><span>{{ $order['discount']['discount_type'] }} discount</span><span>−{{ peso($order['discount']['amount']) }}</span></div>
                    @endif
                    @if ($order['order_type'] === 'delivery')
                        <div><span>Delivery fee</span><span>{{ peso($order['delivery_fee']) }}</span></div>
                    @endif
                    <div class="total"><span>Total</span><span>{{ peso($order['total']) }}</span></div>
                    <div class="fine"><span>VATable sales</span><span>{{ peso($vatable) }}</span></div>
                    <div class="fine"><span>VAT (12%)</span><span>{{ peso($order['total'] - $vatable) }}</span></div>
                </div>
                <div class="r-sep"></div>
                <div class="r-meta">
                    <span>Payment</span><span>{{ \App\Enums\PaymentMethod::from($order['payment_method'])->label() }}{{ $paid ? '' : ' · unpaid' }}</span>
                    @if ($paid && $payment)
                        <span>Reference no.</span><span>{{ $payment['reference_no'] }}</span>
                        <span>Paid on</span><span>{{ Format::dateTime($payment['paid_at']) }}</span>
                        <span>Status</span><span style="color:var(--good);font-weight:600">PAID</span>
                    @endif
                </div>
                <div class="r-foot">Thank you for visiting {{ config('cafe.name') }}!</div>
            </article>
        </div>
    </div>

    <div class="action-bar">
        <a class="btn btn-primary" href="{{ route('customer.orders.show', $order['public_token']) }}">Track my order</a>
        <a class="btn btn-ghost" href="{{ route('customer.menu') }}">Order something else</a>
    </div>
@endsection
