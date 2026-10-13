@extends('layouts.staff', ['module' => 'admin', 'view' => 'table-qr', 'active' => 'dashboard'])

@section('title', 'Table QR Codes · Brew & Bite Café')
@section('heading', 'Table QR codes')
@section('sub', 'Print and place one standee on each table and at the counter')
@section('actions')
    <button class="btn btn-primary btn-sm" type="button" data-print>Print the sheet</button>
@endsection

@section('content')
    <p class="sample-note no-print">Each code opens that table’s menu. The links are signed, so a customer can’t change the table number by editing the address. If the café’s address (APP_URL) changes, print the sheet again.</p>

    <div class="qr-sheet">
        @foreach ($cards as $card)
            <figure class="qr-card">
                <figcaption><b>{{ $card['title'] }}</b><small>{{ $card['sub'] }}</small></figcaption>
                <div class="qr-code" role="img" aria-label="QR code for {{ $card['title'] }}">{!! $card['svg'] !!}</div>
                <p>Scan to order</p>
                <small class="qr-url no-print">{{ Str::limit($card['url'], 64) }}</small>
            </figure>
        @endforeach
    </div>
@endsection
