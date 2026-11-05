<SiteLayout title="My orders" :user="$user">
    <section class="receipt">
        <p class="eyebrow">Your account</p>
        <h1>My orders</h1>
        @if($page->items === [])
            <p>You have no orders yet. <a href="/shop">Browse the catalogue</a>.</p>
        @else
            @foreach($page->items as $order)
                <article>
                    <h2><a href="/shop/orders/{{ $order->id }}">Order #{{ $order->id }}</a></h2>
                    <p>{{ $order->created_at?->format('Y-m-d H:i') }} · {{ $order->status->value }} · {{ $order->items }} items · $ {{ number_format($order->total / 100, 2) }}</p>
                </article>
            @endforeach
            @if($page->hasMore)
                <p><a href="/shop/orders?cursor={{ rawurlencode($page->nextCursor) }}">Older orders</a></p>
            @endif
        @endif
    </section>
</SiteLayout>
