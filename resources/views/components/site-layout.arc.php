@props(string $title, ?\App\User $user = null, string $slot = '', string $head = '')
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }} · Crab Shop</title>
    <style>
        :root { color-scheme: light; font-family: system-ui, sans-serif; color: #17202a; background: #f7f4ee; }
        * { box-sizing: border-box; }
        body { margin: 0; line-height: 1.5; }
        a { color: #145c55; }
        .shell { width: min(1180px, calc(100% - 2rem)); margin: 0 auto; }
        .topbar { background: #173f3a; color: #fff; }
        .topbar .shell { display: flex; align-items: center; justify-content: space-between; gap: 1rem; padding: 1rem 0; }
        .brand { color: #fff; font-weight: 800; text-decoration: none; letter-spacing: .04em; }
        nav { display: flex; flex-wrap: wrap; align-items: center; gap: 1rem; }
        nav a { text-decoration: none; }
        .topbar nav a { color: #d9f2e5; }
        nav form { margin: 0; }
        nav button { width: auto; padding: .35rem .65rem; }
        main { padding: 2rem 0 4rem; }
        h1, h2, h3 { line-height: 1.1; }
        .eyebrow { color: #68746f; font-size: .78rem; font-weight: 700; letter-spacing: .12em; text-transform: uppercase; }
        .shop-grid { display: grid; grid-template-columns: 230px 1fr; gap: 2rem; align-items: start; }
        .filters, .card, .product-detail, .cart-panel, .checkout-panel, .receipt { background: #fff; border: 1px solid #e2ded5; border-radius: 12px; padding: 1rem; box-shadow: 0 8px 24px #173f3a0d; }
        .filters { position: sticky; top: 1rem; }
        label { display: block; margin: .8rem 0 .25rem; font-size: .88rem; font-weight: 650; }
        input, select, button { width: 100%; border: 1px solid #b9c2bd; border-radius: 7px; padding: .65rem .7rem; font: inherit; }
        button, .button { background: #d96545; color: #fff; border: 0; cursor: pointer; font-weight: 750; text-align: center; text-decoration: none; }
        button:hover, .button:hover { background: #bd5035; }
        button:disabled { background: #68746f; cursor: wait; }
        .button.secondary { background: #173f3a; }
        .catalogue-head { display: flex; justify-content: space-between; align-items: end; gap: 1rem; margin-bottom: 1rem; }
        .catalogue-head h1 { margin-bottom: .25rem; }
        .product-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 1rem; }
        .card { display: flex; flex-direction: column; gap: .55rem; }
        .card h2 { font-size: 1.08rem; margin: 0; }
        .card p { margin: 0; color: #68746f; }
        .price { font-size: 1.1rem; font-weight: 800; }
        .stock { font-size: .85rem; color: #287253; }
        .stock.out { color: #a14338; }
        .card .button, .card button { margin-top: auto; }
        .pagination { display: flex; justify-content: center; gap: .4rem; margin-top: 2rem; }
        .pagination a { color: #145c55; padding: .4rem .7rem; border: 1px solid #b9c2bd; border-radius: 7px; text-decoration: none; }
        .pagination a.current { background: #173f3a; color: #fff; }
        .product-detail { display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; }
        .product-detail .price { font-size: 1.7rem; }
        .detail-list { display: grid; grid-template-columns: max-content 1fr; gap: .35rem 1rem; }
        .detail-list dt { color: #68746f; }
        .detail-list dd { margin: 0; font-weight: 650; }
        .cart-panel, .checkout-panel, .receipt { max-width: 720px; margin: 0 auto; }
        .cart-line { display: grid; grid-template-columns: 1fr 90px max-content; gap: .75rem; align-items: center; border-bottom: 1px solid #e2ded5; padding: .75rem 0; }
        .cart-line:last-child { border-bottom: 0; }
        .cart-line input { width: 90px; }
        .checkout-line { display: flex; justify-content: space-between; gap: 1rem; border-bottom: 1px solid #e2ded5; padding: .75rem 0; }
        .checkout-line > div { min-width: 0; overflow-wrap: anywhere; }
        .checkout-line p { margin: 0; }
        .checkout-line > strong { white-space: nowrap; }
        .total { display: flex; justify-content: space-between; font-size: 1.15rem; font-weight: 800; padding-top: 1rem; }
        .notice { padding: .75rem 1rem; background: #edf5ef; border-left: 4px solid #287253; border-radius: 5px; }
        footer { border-top: 1px solid #e2ded5; padding: 1.5rem 0 3rem; color: #68746f; }
        @media (max-width: 850px) { .shop-grid, .product-detail { grid-template-columns: 1fr; } .filters { position: static; } .product-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
        @media (max-width: 560px) { .topbar .shell, .catalogue-head { align-items: flex-start; flex-direction: column; } .product-grid { grid-template-columns: 1fr; } .cart-line { grid-template-columns: 1fr; } .cart-line input { width: 100%; } }
    </style>
    {!! $head !!}
</head>
<body>
<header class="topbar">
    <div class="shell">
        <a class="brand" href="/shop">CRAB SHOP</a>
        <nav aria-label="Shop">
            <a href="/shop">Catalogue</a>
            <a href="/shop/cart">Cart <span data-cart-count>0</span></a>
            @if($user !== null)
                <a href="/shop/orders">My orders</a>
                <span>Logged in as {{ $user->name }} ({{ $user->email }})</span>
                <form method="post" action="/shop/logout"><button type="submit">Log out</button></form>
            @else
                <a href="/shop/login">Log in</a>
            @endif
        </nav>
    </div>
</header>
<main class="shell">{!! $slot !!}</main>
<footer><div class="shell">A server-rendered Crab sample, powered by Arc.</div></footer>
<script>
(() => {
    const key = 'crab-shop-cart';
    const checkoutKey = 'crab-shop-checkout';
    const escapeHtml = value => String(value).replace(/[&<>"']/g, character => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[character]));
    const read = () => { try { return JSON.parse(localStorage.getItem(key) || '[]'); } catch (_) { return []; } };
    const write = cart => { localStorage.setItem(key, JSON.stringify(cart)); updateCount(); };
    const updateCount = () => document.querySelectorAll('[data-cart-count]').forEach(el => el.textContent = read().reduce((n, line) => n + line.quantity, 0));
    const loadProducts = async cart => Object.fromEntries(await Promise.all(cart.map(async line => {
        const response = await fetch('/products/' + encodeURIComponent(line.productId));
        if (response.status === 404) return [line.productId, null];
        if (!response.ok) throw new Error('Product lookup failed');
        return [line.productId, await response.json()];
    })));
    // Only the successful POST supplies this fragment. Ordinary receipt visits do nothing.
    if (document.querySelector('[data-order-receipt]') && location.hash.startsWith('#checkout=')) {
        let pending;
        try { pending = JSON.parse(sessionStorage.getItem(checkoutKey)); } catch (_) {}
        if (pending && location.hash === '#checkout=' + pending.id) {
            const purchased = new Map(pending.cart.map(line => [line.productId, line.quantity]));
            write(read().map(line => ({ ...line, quantity: line.quantity - (purchased.get(line.productId) || 0) })).filter(line => line.quantity > 0));
            sessionStorage.removeItem(checkoutKey);
            history.replaceState(null, '', location.pathname + location.search);
        }
    }
    document.addEventListener('click', event => {
        const button = event.target.closest('[data-add-to-cart]');
        if (!button) return;
        event.preventDefault();
        const id = Number(button.dataset.addToCart);
        const cart = read();
        const line = cart.find(item => item.productId === id);
        if (line) line.quantity = Math.min(20, line.quantity + 1); else cart.push({ productId: id, quantity: 1 });
        write(cart);
        button.textContent = 'Added';
        setTimeout(() => { button.textContent = 'Add to cart'; }, 900);
    });
    const cartLines = document.querySelector('[data-cart-lines]');
    if (cartLines) {
        const cart = read();
        loadProducts(cart).then(products => {
            cartLines.innerHTML = cart.length ? cart.map(line => {
                const product = products[line.productId];
                const id = escapeHtml(line.productId);
                const details = product
                    ? '<a href="/shop/products/' + id + '">' + escapeHtml(product.name) + '</a><input aria-label="Quantity for ' + escapeHtml(product.name) + '" type="number" min="1" max="20" value="' + escapeHtml(line.quantity) + '" data-cart-quantity="' + id + '">'
                    : '<p>Product #' + id + ' is no longer available.</p>';
                return '<div class="cart-line">' + details + '<button type="button" data-remove="' + id + '">Remove</button></div>';
            }).join('') : '<p>Your cart is empty. <a href="/shop">Browse the catalogue</a>.</p>';
            document.querySelectorAll('[data-cart-quantity]').forEach(input => input.addEventListener('change', () => { const next = read().map(line => line.productId === Number(input.dataset.cartQuantity) ? { ...line, quantity: Math.max(1, Math.min(20, Number(input.value) || 1)) } : line); write(next); }));
            document.querySelectorAll('[data-remove]').forEach(button => button.addEventListener('click', () => { write(read().filter(line => line.productId !== Number(button.dataset.remove))); location.reload(); }));
        }).catch(() => { cartLines.innerHTML = '<p role="alert">We could not load your products. Your cart has been kept. <a href="/shop/cart">Try again</a>.</p>'; });
    }
    const checkout = document.querySelector('[data-checkout-form]');
    if (checkout) {
        const summary = document.querySelector('[data-checkout-summary]');
        const submit = checkout.querySelector('button[type=submit]');
        const money = cents => '$ ' + (cents / 100).toFixed(2);
        let reviewedCart = null;
        let revision = 0;
        const refreshSummary = async () => {
            const currentRevision = ++revision;
            const cart = read();
            reviewedCart = null;
            submit.disabled = true;
            summary.innerHTML = '<p>Loading your order summary…</p>';
            try {
                const products = await loadProducts(cart);
                if (currentRevision !== revision) return;
                if (JSON.stringify(cart) !== JSON.stringify(read())) { refreshSummary(); return; }
                if (!cart.length) {
                    summary.innerHTML = '<p>Your cart is empty. <a href="/shop">Browse the catalogue</a>.</p>';
                    return;
                }
                let total = 0;
                let missing = false;
                summary.innerHTML = cart.map(line => {
                    const product = products[line.productId];
                    if (!product) {
                        missing = true;
                        return '<p>Product #' + escapeHtml(line.productId) + ' is no longer available.</p>';
                    }
                    const subtotal = product.price * line.quantity;
                    total += subtotal;
                    return '<div class="checkout-line" data-checkout-line><div><p>' + escapeHtml(line.quantity) + ' × ' + escapeHtml(product.name) + '</p><small>Unit price: ' + money(product.price) + '</small></div><strong>' + money(subtotal) + '</strong></div>';
                }).join('');
                if (missing) {
                    summary.innerHTML += '<p role="alert">Remove unavailable products from <a href="/shop/cart">your cart</a> before ordering.</p>';
                    return;
                }
                summary.innerHTML += '<p class="total"><span>Total</span><span data-checkout-total>' + money(total) + '</span></p>';
                reviewedCart = JSON.stringify(cart);
                submit.disabled = false;
            } catch (_) {
                if (currentRevision !== revision) return;
                summary.innerHTML = '<p role="alert">We could not load your order summary. Your cart has been kept. <a href="/shop/checkout">Try again</a>.</p>';
            }
        };
        checkout.addEventListener('submit', event => {
            // A preparation failure must never submit empty hidden fields.
            event.preventDefault();
            if (checkout.dataset.submitting) return;
            const cart = read();
            if (reviewedCart !== JSON.stringify(cart)) { refreshSummary(); return; }
            try {
                // getRandomValues also works on ordinary HTTP development hostnames.
                const bytes = crypto.getRandomValues(new Uint8Array(16));
                bytes[6] = (bytes[6] & 0x0f) | 0x40;
                bytes[8] = (bytes[8] & 0x3f) | 0x80;
                const hex = Array.from(bytes, byte => byte.toString(16).padStart(2, '0')).join('');
                const id = [hex.slice(0, 8), hex.slice(8, 12), hex.slice(12, 16), hex.slice(16, 20), hex.slice(20)].join('-');
                sessionStorage.setItem(checkoutKey, JSON.stringify({ id, cart }));
                checkout.querySelector('[name=cart]').value = JSON.stringify(cart);
                checkout.querySelector('[name=checkoutId]').value = id;
            } catch (_) {
                submit.disabled = true;
                summary.innerHTML = '<p role="alert">We could not prepare your order. Your cart has been kept. <a href="/shop/checkout">Try again</a>.</p>';
                return;
            }
            checkout.dataset.submitting = 'true';
            submit.disabled = true;
            checkout.submit();
        });
        window.addEventListener('pageshow', () => {
            delete checkout.dataset.submitting;
            refreshSummary();
        });
        window.addEventListener('storage', event => {
            if (event.key === key && !checkout.dataset.submitting) refreshSummary();
        });
    }
    window.addEventListener('storage', updateCount);
    updateCount();
})();
</script>
</body>
</html>
