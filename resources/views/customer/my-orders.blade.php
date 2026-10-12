@extends('layouts.customer', ['view' => 'track'])

@section('title', 'My orders · Brew & Bite Café')

{{-- Shown only when this phone hasn't placed an order yet; otherwise "My orders" opens the newest order. --}}
@section('content')
    <div class="app">
        <x-customer.bar title="My orders" :back="route('customer.menu')" back-label="Back to menu" />

        <x-empty-state icon="receipt" title="No orders yet" text="Orders you place from this phone will show up here.">
            <a class="btn btn-primary" href="{{ route('customer.menu') }}">Browse the menu</a>
        </x-empty-state>
    </div>
@endsection
