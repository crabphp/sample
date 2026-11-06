@props(string $slot = '')
<div class="page-heading"><div><p class="eyebrow">Workspace / Administration</p><h1>Store overview</h1></div><Badge>Administrator</Badge></div>
<nav class="tabs" aria-label="Administration"><a href="/admin/products">Products</a><a href="/admin/orders">Orders</a><a href="/admin/reports">Daily report</a><a href="/clockwerk">Clockwerk ↗</a></nav>{!! $slot !!}
