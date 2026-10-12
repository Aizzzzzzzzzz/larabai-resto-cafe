@extends('layouts.staff', ['module' => 'inventory', 'view' => 'ingredients', 'active' => 'ingredients'])

@section('title', 'Stock · Brew & Bite Café')
@section('heading', 'Stock')
@section('sub', 'Ingredients are deducted automatically as orders are paid')
@section('actions')
    <span class="clock" data-clock></span>
@endsection

@section('content')
    <x-scaffold-note step="5" mockup="inventory.html">
        <form method="POST" action="{{ route('inventory.ingredients.restock', 'milk') }}">
            @csrf
            <input type="hidden" name="mode" value="add">
            <input type="hidden" name="qty" value="5000">
            <button class="btn btn-primary btn-sm" type="submit">Restock fresh milk (demo)</button>
        </form>
        <a href="{{ route('inventory.ingredients.history', 'milk') }}">Fresh milk history →</a>
    </x-scaffold-note>
@endsection
