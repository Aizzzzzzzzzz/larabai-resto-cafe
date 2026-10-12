@extends('layouts.customer', ['view' => 'track'])

@section('title', 'Track order · Brew & Bite Café')

@section('content')
    <x-scaffold-note step="2" mockup="customer.html?view=track">
        <form method="POST" action="{{ route('customer.orders.cancel', $order) }}">
            @csrf
            <button class="btn btn-danger btn-sm" type="submit">Cancel order (demo)</button>
        </form>
        <a href="{{ route('customer.orders.index') }}">My orders →</a>
    </x-scaffold-note>
@endsection
