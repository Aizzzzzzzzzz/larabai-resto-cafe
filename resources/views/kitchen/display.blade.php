@extends('layouts.kitchen', ['view' => 'display'])

@section('title', 'Kitchen Display · Brew & Bite Café')

@section('content')
    <header class="k-head">
        <a class="brand" href="{{ route('navigator') }}"><span class="brand-mark">B&amp;B</span></a>
        <h1>Kitchen display</h1>
        <div class="k-tools">
            <span class="clock" data-clock></span>
            <x-theme-toggle />
        </div>
    </header>

    @include('kitchen.partials.board')
@endsection
