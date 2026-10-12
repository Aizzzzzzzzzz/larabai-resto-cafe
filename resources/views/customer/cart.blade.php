@extends('layouts.customer', ['view' => 'cart'])

@section('title', 'Your order · Brew & Bite Café')

@section('content')
    <x-scaffold-note step="2" mockup="customer.html?view=cart">
        <form method="POST" action="{{ route('customer.cart.update', 1) }}">
            @csrf
            @method('PATCH')
            <input type="hidden" name="qty" value="2">
            <button class="btn btn-ghost btn-sm" type="submit">Change quantity to 2 (demo)</button>
        </form>
        <form method="POST" action="{{ route('customer.cart.destroy', 1) }}">
            @csrf
            @method('DELETE')
            <button class="btn btn-ghost btn-sm" type="submit">Remove line (demo)</button>
        </form>
        <a href="{{ route('customer.checkout.create') }}">Checkout →</a>
    </x-scaffold-note>
@endsection
