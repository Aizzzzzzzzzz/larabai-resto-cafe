@extends('layouts.guest')

@section('title', 'Staff login · Brew & Bite Café')

@section('content')
    <x-scaffold-note step="7" mockup="login.html">
        <form method="POST" action="{{ route('login.store') }}">
            @csrf
            <input type="hidden" name="username" value="admin">
            <button class="btn btn-primary" type="submit">Log in as the owner (demo)</button>
        </form>
    </x-scaffold-note>
@endsection
