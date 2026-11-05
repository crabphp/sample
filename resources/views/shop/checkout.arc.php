<SiteLayout title="Checkout" :user="$user">
    <div class="checkout-panel">
        <p class="eyebrow">Checkout</p>
        <h1>Place your order</h1>
        <p class="notice">This order will belong to {{ $user->name }} ({{ $user->email }}). Your cart stays in this browser until you place the order.</p>
        <form method="post" action="/shop/checkout" data-checkout-form>
            <input type="hidden" name="cart" value="">
            <button type="submit">Place order</button>
        </form>
        <p><a href="/shop/cart">Back to cart</a></p>
    </div>
</SiteLayout>
