<SiteLayout title="Checkout" :user="$user" :cart="$cart" :flash="$flash">
<div class="page-heading">
<div>
<p class="eyebrow">Cart / Checkout</p>
<h1>Make it yours.</h1>
<p class="muted">One last look before we get things moving.</p>
</div>
<a class="text-link" href="/cart">Edit your cart ↗</a>
</div>
@if($cart['lines'] === [])<EmptyState title="Nothing to check out yet" message="Your next everyday essential is waiting in the collection." />
@else
<div class="commerce-layout">
<div class="stack">
<Card title="Your account" eyebrow="01 / Customer">
<div class="account-summary">
<span class="avatar">{{ mb_substr($user->name, 0, 1) }}</span>
<div>
<strong>{{ $user->name }}</strong>
<p class="muted">{{ $user->email }}</p>
</div>
</div>
</Card>
<Card title="Review & confirm" eyebrow="02 / Order">
<p class="muted">Your order is saved immediately. You can follow its progress from your account.</p>
<p class="small muted">This store uses simulated payment and fulfilment. No payment details are collected and you will not be charged.</p>
<p class="small muted">Prices and stock are checked again when you place your order.</p>
</Card>
</div>
<Card title="Order summary">
<OrderSummary :cart="$cart" />
<form method="post" action="/checkout" class="stack" data-checkout-form>
<input type="hidden" name="checkoutId" value="{{ $cart['checkoutId'] }}">
<Button>Place order →</Button>
<span class="small muted">{{ $cart['count'] }} items · Delivery included</span>
</form>
</Card>
</div>
@endif
</SiteLayout>
