@props(string $title, string $message, string $href = '/products', string $label = 'Explore the collection', string $slot = '')
<div class="empty-state">
<span class="empty-symbol" aria-hidden="true">◇</span>
<h2>{{ $title }}</h2>
<p>{{ $message }}</p>
<Button :href="$href" variant="secondary">{{ $label }}</Button>{!! $slot !!}</div>
