<SiteLayout title="Checkout">
    <div class="checkout-panel">
        <p class="eyebrow">Checkout</p>
        <h1>Place your order</h1>
        <p class="notice">This sample has token auth for the API, but no cookie auth yet. Enter one of the seeded accounts for this order; the cart itself never leaves your browser until you submit.</p>
        <form method="post" action="/shop/checkout" data-checkout-form>
            <label for="checkout-email">Email</label>
            <input id="checkout-email" name="email" type="email" autocomplete="email" required>
            <label for="checkout-password">Password</label>
            <input id="checkout-password" name="password" type="password" autocomplete="current-password" required>
            <input type="hidden" name="cart" value="">
            <button type="submit">Place order</button>
        </form>
        <p><a href="/shop/cart">Back to cart</a></p>
    </div>
</SiteLayout>
