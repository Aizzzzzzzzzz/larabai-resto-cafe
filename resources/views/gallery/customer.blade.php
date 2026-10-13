@extends('layouts.customer', ['view' => 'gallery'])

@section('title', 'Components · LaraBai Baristro')

@section('content')
    <div class="app">
        <header class="c-bar">
            <a class="c-back" href="{{ route('navigator.components.staff') }}" aria-label="Staff side">←</a>
            <h1>Shared components</h1>
            <x-theme-toggle />
        </header>

        <div class="pad">
            <section class="card">
                <h2>Menu art</h2>
                <div style="display:flex;gap:16px">
                    <span class="thumb"><x-art :item="$drink" /></span>
                    <span class="thumb"><x-art :item="$meal" /></span>
                </div>
            </section>

            @foreach ([8, 9, 3, 5] as $id)
                @php($order = $orders[$id - 1])
                <section class="card">
                    <div class="card-head">
                        <h2>{{ \App\Support\Format::orderType($order['order_type'], $order['table_no']) }}{{ $order['is_cod'] ? ' · cash on delivery' : '' }}</h2>
                        <x-status-pill :status="$order['status']" :label="$order['order_no']" />
                    </div>
                    <x-tracker :order="$order" />
                    <div class="r-sep"></div>
                    <div style="display:grid;gap:8px">
                        <x-order-lines :lines="$order['items']" />
                    </div>
                </section>
            @endforeach
        </div>

        <x-empty-state title="Your order is empty" text="Add a drink or a meal from the menu to get started.">
            <a class="btn btn-primary" href="{{ route('customer.menu') }}">Browse the menu</a>
        </x-empty-state>
        <x-empty-state icon="receipt" title="No orders yet" text="Orders you place from this phone will show up here." />
    </div>
@endsection
