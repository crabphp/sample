<SiteLayout title="Order {{ $order['id'] }}" :user="$user">
    <div class="receipt">
        <p class="eyebrow">Order confirmed</p>
        <h1>Thanks for your order</h1>
        <p class="notice">Order #{{ $order['id'] }} is <strong>{{ $order['status'] }}</strong>. We have recorded it and the sample's queue will handle its follow-up work.</p>
        <h2>Summary</h2>
        @foreach($lines as $line)
            <p>{{ $line['quantity'] }} × {{ $line['name'] }} · $ {{ number_format($line['price'] * $line['quantity'] / 100, 2) }}</p>
        @endforeach
        <p class="total"><span>Total</span><span>$ {{ number_format($order['total'] / 100, 2) }}</span></p>
        <p><a href="/shop/orders">My orders</a></p>
        <p><a class="button secondary" href="/shop">Return to catalogue</a></p>
    </div>
</SiteLayout>
