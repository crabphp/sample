@props(array $product, string $slot = '')
<article class="product-card">
<a class="art-link" href="/products/{{ $product['id'] }}" tabindex="-1" aria-hidden="true">
<ProductArt :category="$product['category']" :name="$product['name']" />
</a>
<div class="product-card-info">
<p class="eyebrow">{{ $product['category'] }}</p>
<div class="product-card-title">
<h3>
<a href="/products/{{ $product['id'] }}">{{ $product['name'] }}</a>
</h3>
<span class="mono">${{ number_format($product['price'] / 100, 2) }}</span>
</div>
<div class="product-card-bottom">
<span class="small muted">
@if($product['stock'] > 0)<span class="dot" aria-hidden="true">
</span> In stock@else Out of stock@endif
</span>
<form method="post" action="/cart/{{ $product['id'] }}/add">
<input type="hidden" name="actionId" value="{{ App\Shop\Carts::uuid() }}">
<Button variant="secondary" :disabled="$product['stock'] <= 0">
@if($product['stock'] > 0)Add to cart <span aria-hidden="true">+</span>
@else Sold out@endif
</Button>
</form>
</div>
</div>{!! $slot !!}
</article>
