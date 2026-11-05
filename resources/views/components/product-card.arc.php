@props(array $product, string $slot = '')
<article class="card">
    <p class="eyebrow">{{ $product['category'] }}</p>
    <h2><a href="/shop/products/{{ $product['id'] }}">{{ $product['name'] }}</a></h2>
    <p class="price">$ {{ number_format($product['price'] / 100, 2) }}</p>
    @if($product['stock'] > 0)
        <p class="stock">In stock · {{ $product['stock'] }} available</p>
        <button type="button" data-add-to-cart="{{ $product['id'] }}">Add to cart</button>
    @else
        <p class="stock out">Currently sold out</p>
        <button type="button" disabled>Sold out</button>
    @endif
    {!! $slot !!}
</article>
