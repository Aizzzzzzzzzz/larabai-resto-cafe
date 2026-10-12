@props(['lines'])

{{-- Item rows for carts, receipts and tickets (mockup lineRows): qty ×, name, options, note, amount. --}}
@foreach ($lines as $line)
    @php($options = \App\Support\Format::optionText($line['options']))
    <div class="li">
        <span class="li-qty">{{ $line['qty'] }}×</span>
        <span class="li-main">
            <b>{{ $line['name'] }}</b>
            @if ($options)<small>{{ $options }}</small>@endif
            @if ($line['notes'])<small class="li-note">Note: {{ $line['notes'] }}</small>@endif
        </span>
        <span class="li-amt num">{{ peso($line['unit_price'] * $line['qty']) }}</span>
    </div>
@endforeach
