<SiteLayout title="Checkout" :user="$user">
    <div class="checkout-panel">
        <p class="eyebrow">Checkout</p>
        <h1>Place your order</h1>
        <p class="notice">This order will belong to {{ $user->name }} ({{ $user->email }}). Your cart stays in this browser until you place the order.</p>
        <section aria-labelledby="checkout-summary-title">
            <h2 id="checkout-summary-title">Order summary</h2>
            <div data-checkout-summary aria-live="polite"><p>Loading your order summary…</p></div>
        </section>
        <p>Prices and availability are checked again when you place your order.</p>
        <form method="post" action="/shop/checkout" data-checkout-form>
            <input type="hidden" name="cart" value="">
            <input type="hidden" name="checkoutId" value="">
            <button type="submit" disabled>Place order</button>
        </form>
        <p><a href="/shop/cart">Back to cart</a></p>
    </div>
</SiteLayout>
