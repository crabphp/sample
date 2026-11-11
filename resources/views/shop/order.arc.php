<SiteLayout title="Order #{{ $order->id }}" :user="$user" :cart="$cart" :flash="$flash">
<div class="page-heading">
<div>
<p class="eyebrow">Your account / Orders</p>
<h1>Thanks for your order.</h1>
<p class="muted">A few good things are on their way to becoming yours.</p>
</div>
<a class="text-link" href="/orders">All orders ↗</a>
</div>
<div class="commerce-layout" data-order-receipt>
<Card title="Order #{{ $order->id }}" eyebrow="Order confirmed">
<div class="live-order" data-order="{{ $order->id }}" data-version="{{ $order->version }}">
<div class="summary-line">
<span>Current status</span>
<Badge>
<span data-order-status>{{ ucfirst($order->status->value) }}</span>
</Badge>
</div>
<p class="small muted" data-order-connection>Refresh this page for the latest status.</p>
</div>
<div class="order-steps">
<span>01 / Confirmed</span>
<span>02 / Paid</span>
<span>03 / Shipped</span>
</div>
<p class="muted">We’ll keep your order here so you can check in at any time.</p>
<a class="small text-link" href="/orders/{{ $order->id }}">Refresh order status ↗</a>
</Card>
<Card title="Your selection">
@foreach($lines as $line)<div class="summary-line">
<div>
<strong>{{ $line->product->name }}</strong>
<p class="small muted">{{ $line->quantity }} × ${{ number_format($line->price / 100, 2) }}</p>
</div>
<span class="mono">${{ number_format($line->subtotal() / 100, 2) }}</span>
</div>
@endforeach
<div class="summary-total">
<strong>Total</strong>
<strong class="mono">${{ number_format($order->total / 100, 2) }}</strong>
</div>
<Button href="/products" variant="secondary">Continue exploring →</Button>
</Card>
</div>
</SiteLayout>
