@extends('layouts.staff', ['module' => 'admin', 'view' => 'menu', 'active' => 'menu'])

@section('title', 'Menu & Prices · Brew & Bite Café')
@section('heading', 'Menu & prices')
@section('sub', 'Each item lists the ingredients it uses; selling it deducts them from Stock')
@section('actions')
    <span class="clock" data-clock></span>
@endsection

@section('content')
    <x-scaffold-note step="6" mockup="admin.html?view=menu" />
@endsection
