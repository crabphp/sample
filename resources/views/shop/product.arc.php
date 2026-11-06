<SiteLayout :title="$product['name']" :user="$user" :cart="$cart" :flash="$flash">
<nav class="breadcrumbs" aria-label="Breadcrumb">
<a href="/products">Collection</a>
<span>/</span>
<a href="/products?category={{ rawurlencode($product['category']) }}">{{ ucfirst($product['category']) }}</a>
<span>/</span>
<span>{{ $product['name'] }}</span>
</nav>
<article class="product-detail">
<div class="product-detail-art">
<ProductArt :category="$product['category']" :name="$product['name']" />
<span class="mono small">{{ $product['sku'] }} / THE CRAB COLLECTION</span>
</div>
<div class="product-detail-copy">
<p class="eyebrow">{{ $product['category'] }} / Everyday essentials</p>
<h1>{{ $product['name'] }}</h1>
<p class="detail-price mono">${{ number_format($product['price'] / 100, 2) }} <span class="small muted">USD</span>
</p>
<p class="muted">A considered addition to your everyday. Part of our {{ $product['category'] }} collection.</p>
<div class="stock-widget" data-stock="{{ $product['id'] }}">
<p>
<span class="dot">
</span> <span data-stock-value>{{ $product['stock'] }}</span> available</p>
<span class="small muted" data-stock-status>Stock checked on this visit</span>
</div>
<form method="post" action="/cart/{{ $product['id'] }}/add" class="product-purchase">
<input type="hidden" name="actionId" value="{{ App\Shop\Carts::uuid() }}">
<Input name="quantity" label="Quantity" type="number" value="1" min="1" max="20" :required="true" />
<Button :disabled="$product['stock'] <= 0">
@if($product['stock'] > 0)Add to cart <span aria-hidden="true">+</span>
@else Sold out@endif
</Button>
</form>
<p class="small muted">Delivery included. Availability confirmed at checkout.</p>
<div class="detail-specs">
<h2>The details</h2>
<dl>
<dt>SKU</dt>
<dd class="mono">{{ $product['sku'] }}</dd>
<dt>Collection</dt>
<dd>{{ ucfirst($product['category']) }}</dd>
@foreach($details as $name => $value)<dt>{{ ucfirst($name) }}</dt>
<dd>{{ is_bool($value) ? ($value ? 'Yes' : 'No') : (is_scalar($value) ? $value : '—') }}</dd>
@endforeach
</dl>
</div>
</div>
</article>
</SiteLayout>
