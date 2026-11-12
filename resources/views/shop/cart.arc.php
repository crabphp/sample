<SiteLayout title="Your cart" :user="$user" :cart="$cart" :flash="$flash">
<div class="page-heading">
<div>
<p class="eyebrow">Your selection</p>
<h1>A few good choices.</h1>
<p class="muted">{{ $cart['count'] }} items in your cart. Ready when you are.</p>
</div>
<a class="text-link" href="/products">Continue browsing ↗</a>
</div>
@if($cart['lines'] === [])<EmptyState title="Your cart is waiting" message="Find something that makes the everyday a little better." />
@else
<div class="commerce-layout">
<section class="cart-items" aria-label="Cart items">
@foreach($cart['lines'] as $line)<article class="cart-line">
<div class="cart-art">
<ProductArt :category="$line['product']['category'] ?? 'home'" :name="$line['product']['name'] ?? ''" />
</div>
<div class="cart-item-detail">
<h2>
@if($line['product'] !== null)<a href="/products/{{ $line['id'] }}">{{ $line['product']['name'] }}</a>
@else Unavailable product #{{ $line['id'] }}@endif
</h2>
<p class="small muted">
@if($line['product'] !== null)${{ number_format($line['product']['price'] / 100, 2) }} each · {{ $line['product']['category'] }}@else Remove this item to continue.@endif
</p>
@if($line['product'] !== null && $line['quantity'] > $line['product']['stock'])<p class="small">Only {{ $line['product']['stock'] }} available. Adjust your quantity before checkout.</p>
@endif
<form method="post" action="/cart/{{ $line['id'] }}/update" class="quantity-form">
<input type="hidden" name="actionId" value="{{ App\Shop\Carts::uuid() }}">
<Input name="quantity" id="quantity-{{ $line['id'] }}" label="Quantity" type="number" :value="(string) $line['quantity']" min="1" max="20" required />
<Button variant="secondary">Update</Button>
</form>
</div>
<div class="cart-item-end">
<strong class="mono">${{ number_format($line['subtotal'] / 100, 2) }}</strong>
<form method="post" action="/cart/{{ $line['id'] }}/remove">
<input type="hidden" name="actionId" value="{{ App\Shop\Carts::uuid() }}">
<Button variant="ghost">Remove</Button>
</form>
</div>
</article>
@endforeach
</section>
<div>
<Card title="Order summary">
<OrderSummary :cart="$cart" />
<div class="stack">
<Button href="/checkout">Continue to checkout →</Button>
<p class="small muted">
@if($user === null)Your cart is saved. Sign in at checkout to keep it with your account.@else Your cart is saved to your account.@endif
</p>
</div>
</Card>
</div>
</div>
@endif
</SiteLayout>
