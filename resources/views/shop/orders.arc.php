<SiteLayout title="My orders" :user="$user" :cart="$cart" :flash="$flash">
<div class="page-heading">
<div>
<p class="eyebrow">Your account</p>
<h1>My orders</h1>
<p class="muted">The things you chose. Every step of the way.</p>
</div>
<Button href="/products" variant="secondary">Explore the collection ↗</Button>
</div>
@if($page->items === [])<EmptyState title="You have no orders yet." message="Your first good find is just around the corner." />
@else
<Table caption="Your orders">
<Slot name="head">
<tr>
<th scope="col">Order</th>
<th scope="col">Placed</th>
<th scope="col">Status</th>
<th scope="col">Items</th>
<th scope="col">Total</th>
<th scope="col">
<span class="sr-only">Details</span>
</th>
</tr>
</Slot>
@foreach($page->items as $order)<tr>
<td>
<a class="mono" href="/orders/{{ $order->id }}">#{{ $order->id }}</a>
</td>
<td>{{ $order->created_at?->format('M j, Y') }}</td>
<td>
<Badge>{{ ucfirst($order->status->value) }}</Badge>
</td>
<td>{{ $order->items }}</td>
<td class="mono">${{ number_format($order->total / 100, 2) }}</td>
<td>
<a href="/orders/{{ $order->id }}" aria-label="View order {{ $order->id }}">View →</a>
</td>
</tr>
@endforeach
</Table>
@if($page->hasMore)<div class="pagination">
<a class="page-link" href="/orders?cursor={{ rawurlencode($page->nextCursor) }}">Older orders →</a>
</div>
@endif
@endif
</SiteLayout>
