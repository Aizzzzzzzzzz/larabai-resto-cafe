@extends('layouts.customer', ['view' => 'cart'])

@section('title', 'Your order · Brew & Bite Café')

@section('content')
    <div class="app">
        <x-customer.bar title="Your order" :back="route('customer.menu')" back-label="Back to menu" />

        @if (! $lines)
            <x-empty-state title="Your order is empty" text="Add a drink or a meal from the menu to get started.">
                <a class="btn btn-primary" href="{{ route('customer.menu') }}">Browse the menu</a>
            </x-empty-state>
        @else
            <div class="pad">
                <div class="card">
                    <div class="card-head"><h2>Items</h2><a class="link-btn" href="{{ route('customer.menu') }}">+ Add more</a></div>
                    <div>
                        @foreach ($lines as $line)
                            @php($options = \App\Support\Format::optionText($line['options']))
                            <div class="c-line">
                                <span><b>{{ $line['name'] }}</b>
                                    @if ($options)<small>{{ $options }}</small>@endif
                                    @if ($line['notes'])<small><i>Note: {{ $line['notes'] }}</i></small>@endif
                                    @if ($line['sold_out'])<small style="color:var(--danger)">Sold out — remove it to continue</small>@endif
                                    <small>{{ peso($line['unit_price']) }} each</small>
                                </span>
                                <span class="amt">{{ peso($line['unit_price'] * $line['qty']) }}</span>
                                {{-- − / + submit the line's quantity form with the new number --}}
                                <div class="stepper">
                                    <button type="submit" form="qty-{{ $line['id'] }}" name="qty" value="{{ $line['qty'] - 1 }}" aria-label="Decrease {{ $line['name'] }}">−</button>
                                    <output>{{ $line['qty'] }}</output>
                                    <button type="submit" form="qty-{{ $line['id'] }}" name="qty" value="{{ min($line['qty'] + 1, \App\Services\CustomerCart::MAX_QTY) }}" aria-label="Increase {{ $line['name'] }}">+</button>
                                </div>
                                <button type="submit" form="remove-{{ $line['id'] }}" class="link-btn remove" style="color:var(--danger)">Remove</button>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="card">
                    <div class="sum">
                        <div><span>Subtotal</span><span>{{ peso($subtotal) }}</span></div>
                        <div class="fine"><span>A delivery fee is added at checkout for delivery orders.</span></div>
                    </div>
                </div>
            </div>

            <div class="action-bar">
                <a class="btn btn-primary" href="{{ route('customer.checkout.create') }}">Continue to checkout · {{ peso($subtotal) }}</a>
            </div>

            @foreach ($lines as $line)
                <form id="qty-{{ $line['id'] }}" method="POST" action="{{ route('customer.cart.update', $line['id']) }}" hidden>
                    @csrf
                    @method('PATCH')
                </form>
                <form id="remove-{{ $line['id'] }}" method="POST" action="{{ route('customer.cart.destroy', $line['id']) }}" hidden>
                    @csrf
                    @method('DELETE')
                </form>
            @endforeach
        @endif
    </div>
@endsection
