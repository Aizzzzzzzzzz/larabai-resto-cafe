@extends('layouts.customer', ['view' => 'track'])

@section('title', 'Track order · LaraBai Baristro')

@php
    use App\Enums\OrderStatus;
    use App\Support\CustomerOrderText;
    use App\Support\Format;

    [$title, $text] = $hero;
@endphp

@section('content')
    <div class="app" data-poll="{{ route('customer.orders.status', $order['public_token']) }}" data-status="{{ $order['status'] }}">
        <x-customer.bar title="My orders" :back="route('customer.menu')" back-label="Back to menu" />

        @if (count($mine) > 1)
            <nav class="order-tabs" aria-label="Your orders">
                @foreach ($mine as $o)
                    <a href="{{ route('customer.orders.show', $o['public_token']) }}" @class(['on' => $o['id'] === $order['id']])>{{ substr($o['order_no'], -4) }} · {{ OrderStatus::from($o['status'])->label() }}</a>
                @endforeach
            </nav>
        @endif

        <div class="track-hero" style="margin-top:12px">
            <x-status-pill :status="$order['status']" :label="$order['order_no']" />
            <h2>{{ $title }}</h2>
            <p>{{ $text }}</p>
        </div>

        <div class="pad">
            <section class="card"><h2>Progress</h2><x-tracker :order="$order" /></section>
            <section class="card">
                <div class="card-head"><h2>Order details</h2><a class="link-btn" href="{{ route('customer.orders.success', $order['public_token']) }}">View receipt</a></div>
                <div class="r-meta">
                    <span>Order no.</span><span>{{ $order['order_no'] }}</span>
                    <span>Placed</span><span>{{ Format::dateTime($order['created_at']) }}</span>
                    <span>Type</span><span>{{ Format::orderType($order['order_type'], $order['table_no']) }}</span>
                    <span>Payment</span><span>{{ CustomerOrderText::paymentState($order) }}</span>
                    <span>Total</span><span>{{ peso($order['total']) }}</span>
                </div>
                <div class="r-sep"></div>
                <div style="display:grid;gap:8px">
                    <x-order-lines :lines="$order['items']" />
                </div>
            </section>
            <div class="btn-row">
                @if (CustomerOrderText::canCancel($order))
                    <form method="POST" action="{{ route('customer.orders.cancel', $order['public_token']) }}" style="display:contents">
                        @csrf
                        <button class="btn btn-danger" type="submit" data-confirm-twice="Tap again to confirm cancel">Cancel order</button>
                    </form>
                @endif
                <a class="btn btn-primary" href="{{ route('customer.menu') }}">Order more</a>
            </div>
        </div>
    </div>
@endsection
