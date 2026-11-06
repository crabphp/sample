@props(array $products, string $slot = '')
<div class="product-grid" aria-label="Products">
@foreach($products as $product)<ProductCard :product="$product" />
@endforeach
</div>{!! $slot !!}
