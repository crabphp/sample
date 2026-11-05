<SiteLayout title="Log in" :user="$user">
    <div class="checkout-panel">
        <p class="eyebrow">Your account</p>
        <h1>Log in to the shop</h1>
        <p>Log in to check out and see your orders. Your cart stays in this browser.</p>
        @if($error !== null)
            <p class="notice" role="alert">{{ $error }}</p>
        @endif
        <form method="post" action="/shop/login">
            <label for="login-email">Email</label>
            <input id="login-email" name="email" type="email" autocomplete="username" value="{{ $email }}" required>
            <label for="login-password">Password</label>
            <input id="login-password" name="password" type="password" autocomplete="current-password" maxlength="72" required>
            <button type="submit">Log in</button>
        </form>
        <p><a href="/shop/cart">Back to cart</a></p>
    </div>
</SiteLayout>
