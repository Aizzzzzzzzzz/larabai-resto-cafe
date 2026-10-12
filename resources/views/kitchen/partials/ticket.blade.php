@php
    $start = \Illuminate\Support\Carbon::parse(\App\Support\KitchenActions::startedAt($order));
    $seconds = max(0, now()->getTimestamp() - $start->getTimestamp());
    $min = intdiv($seconds, 60);
    [$go, $goLabel] = match ($col) {
        'new' => ['start', 'Start preparing'],
        'prep' => ['ready', 'Mark ready'],
        default => ['done', \App\Support\KitchenActions::doneLabel($order)],
    };
    $alt = match ($col) {
        'prep' => ['back', 'Back'],
        'ready' => ['recall', 'Recall'],
        default => null,
    };
@endphp
<article @class(['tk', 'fresh' => $col === 'new' && $seconds < 15]) data-id="{{ $order['id'] }}">
    <div class="tk-head">
        <span class="tk-no">#{{ substr($order['order_no'], -4) }}</span>
        @if ($order['order_type'] === 'dine-in')
            <span class="tk-where dine">TABLE {{ $order['table_no'] }}</span>
        @else
            <span class="tk-where">{{ strtoupper($order['order_type']) }}</span>
        @endif
        <span class="tk-meta">{{ $order['source'] === 'pos' ? 'Counter order' : 'QR order' }}</span>
        <span @class(['tk-time', 'warn' => $min >= 10 && $min < 15, 'late' => $min >= 15]) data-start="{{ $start->toIso8601String() }}">{{ $min }}:{{ str_pad((string) ($seconds % 60), 2, '0', STR_PAD_LEFT) }}</span>
    </div>
    @if ($order['is_cod'] && ! $order['paid_at'])
        <span class="tk-flag">Cash on delivery · rider collects</span>
    @endif
    {{-- Tapping a line ticks it off on this screen only (resources/js/modules/kitchen.js) --}}
    <ul class="tk-items">
        @foreach ($order['items'] as $i => $line)
            @php($options = \App\Support\Format::optionText($line['options']))
            <li data-line="{{ $i }}" title="Tap to tick off">
                <span class="q">{{ $line['qty'] }}×</span>
                <span><b>{{ $line['name'] }}</b>@if ($options)<small>{{ $options }}</small>@endif @if ($line['notes'])<span class="note">{{ $line['notes'] }}</span>@endif</span>
            </li>
        @endforeach
    </ul>
    <div class="tk-foot" @unless ($alt) style="grid-template-columns:1fr" @endunless>
        @if (\App\Support\KitchenActions::can($order, $go))
            <form method="POST" action="{{ route('kitchen.orders.status', $order['id']) }}" style="display:contents">
                @csrf
                @method('PATCH')
                <button class="tk-go" type="submit" name="move" value="{{ $go }}">{{ $goLabel }}</button>
            </form>
        @else
            {{-- No disabled buttons: say why nothing can be done here --}}
            <span class="tk-go" style="text-align:center;cursor:default;opacity:.75">Waiting for cash on delivery</span>
        @endif
        @if ($alt)
            <form method="POST" action="{{ route('kitchen.orders.status', $order['id']) }}" style="display:contents">
                @csrf
                @method('PATCH')
                <button class="tk-alt" type="submit" name="move" value="{{ $alt[0] }}">{{ $alt[1] }}</button>
            </form>
        @endif
    </div>
</article>
