@extends('layouts.customer', ['view' => 'checkout'])

@section('title', 'Checkout · Brew & Bite Café')

@section('content')
    <x-scaffold-note step="2" mockup="customer.html?view=checkout">
        <form method="POST" action="{{ route('customer.checkout.store') }}">
            @csrf
            <input type="hidden" name="order_type" value="dine-in">
            <input type="hidden" name="payment_method" value="GCASH">
            <button class="btn btn-primary" type="submit">Place order (demo)</button>
        </form>
    </x-scaffold-note>
@endsection
