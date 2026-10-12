@extends('layouts.public', ['module' => 'home'])

@section('title', 'Brew & Bite Café · Tarlac City')
@section('description', 'Brew & Bite Café in Tarlac City: espresso drinks, milk tea, silog meals and fresh pastries. Open daily 7 AM to 10 PM.')

@section('content')
    <x-scaffold-note step="7" mockup="home.html">
        @if (config('cafe.demo_mode'))
            <a href="{{ route('navigator') }}">Open the system navigator →</a>
        @endif
    </x-scaffold-note>
@endsection
