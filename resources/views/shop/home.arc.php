<SiteLayout title="Objects for everyday" :user="$user" :cart="$cart" :flash="$flash">
<section class="hero">
<div class="hero-copy">
<p class="eyebrow">
<span class="dot">
</span> THE EVERYDAY COLLECTION · VOL. 01</p>
<h1>Good things.<br>
<span class="muted">Made for your day.</span>
</h1>
<p class="hero-description">Considered objects for the spaces you live in.<br>Useful by nature. Beautiful by design.</p>
<div class="actions">
<Button href="/products">Explore the collection <span aria-hidden="true">↗</span>
</Button>
<Button href="#live" variant="secondary">See the store in motion</Button>
</div>
<div class="hero-note">
<span class="mono">01—05</span>
<span>Five collections. A little more considered.</span>
</div>
</div>
<div class="hero-art">
<span class="hero-art-label mono">FORM / FUNCTION</span>
<ProductArt category="kitchen" />
<div class="hero-art-caption">
<span>Small rituals. Lasting essentials.</span>
<span class="mono">CRAB / 001</span>
</div>
</div>
</section>
<section class="section">
<div class="section-heading">
<div>
<p class="eyebrow">A place for everything</p>
<h2>Find your everyday.</h2>
</div>
<a class="text-link" href="/products">View all products <span aria-hidden="true">↗</span>
</a>
</div>
<div class="category-grid">
@foreach($categories as $category)<a href="/products?category={{ rawurlencode($category['category']) }}" class="category-tile">
<span class="category-symbol" aria-hidden="true">{{ match ($category['category']) { 'kitchen' => '◒', 'desk' => '▤', 'home' => '⌂', 'outdoors' => '△', default => '◇' } }}</span>
<strong>{{ ucfirst($category['category']) }}</strong>
<span class="small muted">{{ $category['products'] }} essentials</span>
<span class="category-arrow" aria-hidden="true">↗</span>
</a>
@endforeach
</div>
</section>
<section class="section">
<div class="section-heading">
<div>
<p class="eyebrow">Selected for you</p>
<h2>A few good essentials.</h2>
</div>
<Badge>The collection</Badge>
</div>
@if($products !== [])<ProductGrid :products="$products" />
@else
<EmptyState title="A new collection is on its way" message="Check back soon for our latest everyday essentials." />
@endif
</section>
<section class="section" id="live">
<div class="section-heading">
<div>
<p class="eyebrow">A store that moves with you</p>
<h2>Connected, from shelf to doorstep.</h2>
</div>
<Badge>
<span class="dot">
</span> Live experiences</Badge>
</div>
<div class="experience-grid">
<Card title="A little help finding it." eyebrow="01 / Shopping assistant">
<p class="muted">Tell us what you have in mind. We’ll help you find something that fits.</p>
<AssistantForm :user="$user" />
</Card>
<div class="experience-stack">
<Card title="On the shelf, right now." eyebrow="02 / Live stock">
@if($products !== [])<?php $stockProduct = $products[0]; ?>
<div data-stock="{{ $stockProduct['id'] }}" class="stock-widget">
<div>
<a href="/products/{{ $stockProduct['id'] }}">{{ $stockProduct['name'] }}</a>
<p class="small muted" data-stock-status>Latest stock. Refresh to check again.</p>
</div>
<div class="stock-number mono">
<span data-stock-value>{{ $stockProduct['stock'] }}</span>
<span class="small muted">available</span>
</div>
</div>
<a class="small text-link" href="/">Refresh availability ↗</a>
@else
<p class="muted">Stock will appear when the collection arrives.</p>
@endif
</Card>
<Card title="Every step, in sight." eyebrow="03 / Order updates">
@if($latestOrder !== null)<div class="live-order" data-order="{{ $latestOrder->id }}" data-version="0">
<div class="summary-line">
<a href="/orders/{{ $latestOrder->id }}">Order #{{ $latestOrder->id }}</a>
<Badge>
<span data-order-status>{{ ucfirst($latestOrder->status->value) }}</span>
</Badge>
</div>
<p class="small muted" data-order-connection>Refresh for the latest status.</p>
<a class="small text-link" href="/orders/{{ $latestOrder->id }}">Track your order ↗</a>
</div>
@else
<p class="muted">From confirmation to dispatch, your order stays close.</p>
<Button href="/orders" variant="secondary">
@if($user === null)Sign in to view orders@else View your orders@endif
</Button>
@endif
</Card>
</div>
</div>
</section>
<section class="section built-section" id="built-with-crab">
<div class="section-heading">
<div>
<p class="eyebrow">Under the surface</p>
<h2>Built with Crab.</h2>
<p class="muted">One application. The whole experience.</p>
</div>
<span class="mono small">PHP 8.4 / CRAB 0.6.2 / ARC 0.1.1</span>
</div>
<div class="feature-grid">
<a class="feature-link" href="/login">
<span class="mono">01 / Identity ↗</span>
<h3>Cookie authentication</h3>
<p>Secure accounts, private orders and a cart that follows you.</p>
</a>
<a class="feature-link" href="https://github.com/crabphp/arc">
<span class="mono">02 / Interface ↗</span>
<h3>Rendered with Arc</h3>
<p>Components and native forms. Ready before JavaScript.</p>
</a>
<a class="feature-link" href="https://github.com/crabphp/sample/blob/main/app/Setup.php">
<span class="mono">03 / Background work ↗</span>
<h3>Queue &amp; scheduler</h3>
<p>Confirmations, stock updates, payments and shipment follow-ups.</p>
</a>
<a class="feature-link" href="/clockwerk">
<span class="mono">04 / Observability ↗</span>
<h3>Clockwerk dashboard</h3>
<p>Explore requests, workers and scheduled jobs. Dashboard access required.</p>
</a>
<a class="feature-link" href="/openapi.json">
<span class="mono">05 / Contract ↗</span>
<h3>OpenAPI specification</h3>
<p>The documented API, generated directly from the application.</p>
</a>
<a class="feature-link" href="/api/products">
<span class="mono">06 / Platform ↗</span>
<h3>The JSON API</h3>
<p>The same products and business rules, available for your next client.</p>
</a>
</div>
<div class="feature-footnote">
<a href="{{ $products === [] ? '/api/products' : '/api/products/' . $products[0]['id'] . '/stock' }}">Stock events ↗</a>
<a href="/assistant">Streaming &amp; actor conversations ↗</a>
<a href="/orders">WebSocket order updates ↗</a>
<a href="/admin/reports">Scheduled daily reports ↗</a>
</div>
</section>
</SiteLayout>
