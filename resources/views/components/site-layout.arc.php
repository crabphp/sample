@props(string $title, ?App\User $user = null, array $cart = [], ?string $flash = null, string $slot = '', string $head = '')
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="color-scheme" content="light dark">
<meta name="description" content="Considered essentials for everyday life. Explore the Crab collection.">
<title>{{ $title }} · Crab</title>
<link rel="stylesheet" href="/assets/shop-v1/shop.css">
<link rel="preload" href="/assets/shop-v1/geist-sans.woff2" as="font" type="font/woff2" crossorigin>
<script src="/assets/shop-v1/shop.js" defer>
</script>
{!! $head !!}
</head>
<body>
<a class="skip-link" href="#main">Skip to content</a>
<header class="site-header">
<div class="shell header-inner">
<a class="brand" href="/" aria-label="Crab home">
<svg viewBox="0 0 32 32" aria-hidden="true">
<path d="M8 12 3 7v8l6 4m15-7 5-5v8l-6 4M9 23l-5 4m19-4 5 4M7 19H2m23 0h5"/>
<path d="M8 18c0-6 16-6 16 0v3c0 7-16 7-16 0z"/>
<path d="M12 12V9m8 3V9"/>
</svg>crab<span class="brand-divider">/</span>
<span class="brand-shop">Store</span>
</a>
<nav class="main-nav" aria-label="Shop">
<a href="/products">Collection</a>
<a href="/assistant">Assistant</a>
</nav>
<form class="header-search" method="get" action="/products" role="search">
<label class="sr-only" for="header-q">Search products</label>
<input type="search" id="header-q" name="q" placeholder="Search the collection…" maxlength="60">
<button type="submit" aria-label="Search">⌕</button>
</form>
<a class="cart-link" href="/cart">Cart <span class="cart-count" data-cart-count>{{ $cart['count'] ?? 0 }}</span>
</a>
@if($user !== null)
<details class="account-menu">
<summary>
<span class="avatar">{{ mb_substr($user->name, 0, 1) }}</span>
<span class="sr-only">Account menu for {{ $user->name }}</span>
</summary>
<div class="account-panel">
<strong>{{ $user->name }}</strong>
<span class="muted small">{{ $user->email }}</span>
<a href="/orders">My orders</a>
@if($user->isAdmin())<a href="/admin">Administration</a>
@endif
<form method="post" action="/logout">
<Button variant="secondary">Sign out</Button>
</form>
</div>
</details>
@else
<a class="sign-in" href="/login">Sign in <span aria-hidden="true">↗</span>
</a>
@endif
</div>
</header>
<main id="main" class="shell" tabindex="-1">
<Flash :message="$flash" />{!! $slot !!}</main>
<footer class="site-footer">
<div class="shell footer-inner">
<a class="brand" href="/">crab<span class="brand-divider">/</span>
<span class="brand-shop">Objects for everyday.</span>
</a>
<nav aria-label="Footer">
<a href="/products">Collection</a>
<a href="/orders">Your orders</a>
<a href="/#built-with-crab">Built with Crab ↗</a>
</nav>
<span class="mono small">Thoughtfully selected. Simply made.</span>
</div>
</footer>
</body>
</html>
