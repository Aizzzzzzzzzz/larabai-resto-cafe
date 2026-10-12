<div class="recent" data-k-part="recent">
    @if ($recent->isEmpty())
        <span>Finished orders will appear here.</span>
    @else
        <span>Recently done:</span>
        @foreach ($recent as $order)
            <form method="POST" action="{{ route('kitchen.orders.status', $order['id']) }}" style="display:contents">
                @csrf
                @method('PATCH')
                <button type="submit" name="move" value="recall" title="Recall to Ready">#{{ substr($order['order_no'], -4) }} ↺</button>
            </form>
        @endforeach
    @endif
</div>
