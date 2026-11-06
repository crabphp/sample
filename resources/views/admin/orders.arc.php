<SiteLayout title="Manage orders" :user="$user" :cart="$cart" :flash="$flash">
<AdminNav />
<div class="section-heading">
<h2>Orders <span class="muted">{{ $page->total }}</span>
</h2>
<p class="small muted">Paid orders are ready to ship.</p>
</div>
@if($page->items === [])<EmptyState title="No orders yet" message="New orders will appear here as customers check out." />
@else
<Table caption="Order fulfilment">
<Slot name="head">
<tr>
<th scope="col">Order</th>
<th scope="col">Customer</th>
<th scope="col">Placed</th>
<th scope="col">Status</th>
<th scope="col">Total</th>
<th scope="col">Action</th>
</tr>
</Slot>
@foreach($page->items as $order)<tr>
<td class="mono">#{{ $order['id'] }}</td>
<td class="mono">#{{ $order['user_id'] }}</td>
<td>{{ $order['created_at'] }}</td>
<td>
<Badge>{{ ucfirst($order['status']) }}</Badge>
</td>
<td class="mono">${{ number_format($order['total'] / 100, 2) }}</td>
<td>
@if($order['status'] === 'paid')<form method="post" action="/admin/orders/{{ $order['id'] }}/ship">
<Button variant="secondary">Ship order →</Button>
</form>
@else
<span class="small muted">{{ $order['status'] === 'pending' ? 'Awaiting payment' : 'Complete' }}</span>
@endif
</td>
</tr>
@endforeach
</Table>
<Pagination :page="$page" path="/admin/orders" />
@endif
</SiteLayout>
