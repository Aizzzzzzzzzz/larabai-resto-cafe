{{--
    Customer screens (phone-first, opened from the table or counter QR).
    Expects: $view. Each screen draws its own header (the mockup's c-head differs per screen).
--}}
@extends('layouts.base', ['module' => 'customer', 'bodyClass' => 'm-customer customer'])

@section('body')
    @yield('content')
@endsection
