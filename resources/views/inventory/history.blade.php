@extends('layouts.staff', ['module' => 'inventory', 'view' => 'history', 'active' => 'ingredients'])

@section('title', 'Stock History · Brew & Bite Café')
@section('heading', 'Stock history')
@section('sub', 'Every stock movement for '.$ingredient)
@section('actions')
    <span class="clock" data-clock></span>
@endsection

@section('content')
    <x-scaffold-note step="5" mockup="inventory.html (history panel)" />
@endsection
