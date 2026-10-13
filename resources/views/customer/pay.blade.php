@extends('layouts.customer', ['view' => 'pay'])

@section('title', 'Payment · LaraBai Baristro')

@php
    $wallet = \App\Enums\PaymentMethod::from($order['payment_method']);
    $brand = $wallet === \App\Enums\PaymentMethod::GCash ? 'gcash' : 'maya';
@endphp

@section('content')
    <div class="app" style="padding-bottom:0">
        <form class="wallet" method="POST" action="{{ route('customer.pay.store', $order['public_token']) }}" data-wallet>
            @csrf
            <div class="wallet-top {{ $brand }}">
                <div class="row">
                    <strong style="font-family:var(--display);font-size:1.2rem">{{ $wallet->label() }}</strong>
                    <span class="sim-tag">SIMULATED</span>
                </div>
                <small>Pay merchant</small>
            </div>

            <div class="wallet-card">
                <div class="wallet-amt">
                    <small>You are paying</small>
                    <strong>{{ peso($order['total']) }}</strong>
                    <small>to <b>{{ config('cafe.name') }}</b> · Tarlac City</small>
                </div>
                <div class="r-sep"></div>
                <dl class="kv">
                    <dt>Order</dt><dd>{{ \App\Support\Format::orderType($order['order_type'], $order['table_no']) }}</dd>
                    <dt>Items</dt><dd>{{ array_sum(array_column($order['items'], 'qty')) }} item(s)</dd>
                    <dt>Account</dt><dd>Demo wallet</dd>
                    <dt>Date</dt><dd>{{ \App\Support\Format::dateTime(now()) }}</dd>
                </dl>
                <p class="wallet-note">This is a simulated payment for the demo. No real money moves and no payment provider is contacted. Tapping Pay saves the payment record and marks your order as paid.</p>
            </div>

            <div class="wallet-foot">
                <button class="btn btn-{{ $brand }}" type="submit" data-wallet-pay>Pay {{ peso($order['total']) }}</button>
                <a class="btn btn-ghost" href="{{ route('customer.checkout.create') }}" data-wallet-cancel>Cancel and go back</a>
            </div>
        </form>
    </div>
@endsection
