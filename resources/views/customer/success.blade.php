@extends('layouts.customer', ['view' => 'success'])

@section('title', 'Order placed · Brew & Bite Café')

@section('content')
    <x-scaffold-note step="2" mockup="customer.html?view=success">
        <a href="{{ route('customer.orders.show', $order) }}">Track this order →</a>
    </x-scaffold-note>
@endsection
