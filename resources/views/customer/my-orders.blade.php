@extends('layouts.customer', ['view' => 'track'])

@section('title', 'My orders · Brew & Bite Café')

@section('content')
    <x-scaffold-note step="2" mockup="customer.html?view=track (My orders)">
        <a href="{{ route('customer.menu') }}">← Back to the menu</a>
    </x-scaffold-note>
@endsection
