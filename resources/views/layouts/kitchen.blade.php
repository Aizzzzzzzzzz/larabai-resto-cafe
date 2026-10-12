{{--
    Kitchen wall display: full-height board, dark by default with its own theme setting.
    Expects: $view.
--}}
@extends('layouts.base', ['module' => 'kitchen', 'bodyClass' => ''])

@section('body')
    @yield('content')
@endsection
