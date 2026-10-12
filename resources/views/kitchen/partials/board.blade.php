{{-- Board columns. Also served alone by kitchen.board so the display can refresh it. --}}
<x-scaffold-note step="4" mockup="kitchen.html">
    <form method="POST" action="{{ route('kitchen.orders.status', 'demo') }}">
        @csrf
        @method('PATCH')
        <input type="hidden" name="to" value="preparing">
        <button class="btn btn-primary btn-sm" type="submit">Start an order (demo)</button>
    </form>
    <form method="POST" action="{{ route('kitchen.availability.update', 'latte') }}">
        @csrf
        @method('PATCH')
        <input type="hidden" name="sold_out" value="1">
        <button class="btn btn-ghost btn-sm" type="submit">Mark Café Latte sold out (demo)</button>
    </form>
</x-scaffold-note>
