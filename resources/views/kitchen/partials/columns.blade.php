@php
    $heads = [
        'new' => ['New', 'col-new', 'No new orders. New paid orders appear here.'],
        'prep' => ['Preparing', 'col-prep', 'Nothing in progress.'],
        'ready' => ['Ready', 'col-ready', 'Nothing waiting to be served.'],
    ];
@endphp
<main class="board" data-k-part="columns" data-new-count="{{ $columns['new']->count() }}">
    @foreach ($heads as $key => [$title, $class, $empty])
        <section class="col {{ $class }}">
            <div class="col-head"><h2>{{ $title }}</h2><span>{{ $columns[$key]->count() }}</span></div>
            <div class="tickets">
                @forelse ($columns[$key] as $order)
                    @include('kitchen.partials.ticket', ['order' => $order, 'col' => $key])
                @empty
                    <p class="empty">{{ $empty }}</p>
                @endforelse
            </div>
        </section>
    @endforeach
</main>
