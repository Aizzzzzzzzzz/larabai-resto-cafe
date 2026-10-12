@extends('layouts.customer', ['view' => 'menu'])

@section('title', 'Menu · Brew & Bite Café')

@section('content')
    <x-scaffold-note step="2" mockup="customer.html">
        <form method="POST" action="{{ route('customer.cart.store') }}">
            @csrf
            <input type="hidden" name="menu_item" value="latte">
            <button class="btn btn-primary" type="submit">Add a Café Latte (demo)</button>
        </form>
        <a href="{{ route('customer.cart.index') }}">View cart →</a>
    </x-scaffold-note>
@endsection
