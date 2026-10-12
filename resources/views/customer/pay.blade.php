@extends('layouts.customer', ['view' => 'pay'])

@section('title', 'Payment · Brew & Bite Café')

@section('content')
    <x-scaffold-note step="2" mockup="customer.html?view=pay">
        <form method="POST" action="{{ route('customer.pay.store', $order) }}">
            @csrf
            <button class="btn btn-gcash" type="submit">Pay with GCash (demo)</button>
        </form>
    </x-scaffold-note>
@endsection
