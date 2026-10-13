@extends('errors.layout')

@section('code', '419')
@section('title', 'This page expired')
@section('text', 'It sat open for too long, so for your safety we didn’t send it. Go back, reload the page and try again. Nothing was saved or charged.')
@section('actions')
    <a class="btn btn-primary" href="{{ str_starts_with((string) request()->headers->get('referer'), url('/').'/') ? request()->headers->get('referer') : url('/') }}">Go back</a>
@endsection
