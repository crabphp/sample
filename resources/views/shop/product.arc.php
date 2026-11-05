<SiteLayout title="{{ $product['name'] }}">
    <p class="eyebrow"><a href="/shop">Catalogue</a> / {{ $product['category'] }}</p>
    <article class="product-detail">
        <div>
            <p class="eyebrow">{{ $product['sku'] }}</p>
            <h1>{{ $product['name'] }}</h1>
            <p class="price">$ {{ number_format($product['price'] / 100, 2) }}</p>
            @if($product['stock'] > 0)
                <p class="stock">In stock · {{ $product['stock'] }} available</p>
                <button type="button" data-add-to-cart="{{ $product['id'] }}">Add to cart</button>
            @else
                <p class="stock out">Currently sold out</p>
            @endif
        </div>
        <div>
            <h2>Details</h2>
            @if($details === [])
                <p>Made for everyday use, with details kept deliberately simple.</p>
            @else
                <dl class="detail-list">
                    @foreach($details as $name => $value)
                        <dt>{{ $name }}</dt><dd>{{ is_bool($value) ? ($value ? 'Yes' : 'No') : $value }}</dd>
                    @endforeach
                </dl>
            @endif
        </div>
    </article>
</SiteLayout>
