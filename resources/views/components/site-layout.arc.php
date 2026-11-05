@props(string $title, string $slot = '', string $head = '')
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
        nav { display: flex; gap: 1rem; }
        nav a { color: #d9f2e5; text-decoration: none; }
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
        .pagination a { padding: .4rem .7rem; border: 1px solid #b9c2bd; border-radius: 7px; text-decoration: none; }
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
        </nav>
    </div>
</header>
<main class="shell">{!! $slot !!}</main>
<footer><div class="shell">A server-rendered Crab sample, powered by Arc.</div></footer>
<script>
(() => {
    const key = 'crab-shop-cart';
    const escapeHtml = value => String(value).replace(/[&<>"']/g, character => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[character]));
    const read = () => { try { return JSON.parse(localStorage.getItem(key) || '[]'); } catch (_) { return []; } };
    const write = cart => { localStorage.setItem(key, JSON.stringify(cart)); updateCount(); };
    const updateCount = () => document.querySelectorAll('[data-cart-count]').forEach(el => el.textContent = read().reduce((n, line) => n + line.quantity, 0));
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
        fetch('/products?perPage=50').then(response => response.json()).then(payload => {
            const products = Object.fromEntries(payload.data.map(product => [product.id, product]));
            const cart = read().filter(line => products[line.productId]);
            write(cart);
            cartLines.innerHTML = cart.length ? cart.map(line => '<div class="cart-line"><a href="/shop/products/' + line.productId + '">' + escapeHtml(products[line.productId].name) + '</a><input aria-label="Quantity for ' + escapeHtml(products[line.productId].name) + '" type="number" min="1" max="20" value="' + line.quantity + '" data-cart-quantity="' + line.productId + '"><button type="button" data-remove="' + line.productId + '">Remove</button></div>').join('') : '<p>Your cart is empty. <a href="/shop">Browse the catalogue</a>.</p>';
            document.querySelectorAll('[data-cart-quantity]').forEach(input => input.addEventListener('change', () => { const next = read().map(line => line.productId === Number(input.dataset.cartQuantity) ? { ...line, quantity: Math.max(1, Math.min(20, Number(input.value) || 1)) } : line); write(next); }));
            document.querySelectorAll('[data-remove]').forEach(button => button.addEventListener('click', () => { write(read().filter(line => line.productId !== Number(button.dataset.remove))); location.reload(); }));
        }).catch(() => { cartLines.innerHTML = '<p>We could not read the catalogue. Try again.</p>'; });
    }
    const checkout = document.querySelector('[data-checkout-form]');
    if (checkout) checkout.addEventListener('submit', event => { const cart = read(); if (!cart.length) { event.preventDefault(); alert('Your cart is empty.'); return; } checkout.querySelector('[name=cart]').value = JSON.stringify(cart); });
    updateCount();
})();
</script>
</body>
</html>
