@props(array $cart, string $slot = '')
<div class="order-summary">
@foreach($cart['lines'] as $line)
<div class="summary-line"><div><strong>{{ $line['product']['name'] ?? 'Unavailable product' }}</strong><p class="small muted">{{ $line['quantity'] }} × ${{ number_format(($line['product']['price'] ?? 0) / 100, 2) }}</p></div><span class="mono">${{ number_format($line['subtotal'] / 100, 2) }}</span></div>
@endforeach
<div class="summary-line"><span class="muted">Delivery</span><span>Included</span></div>
<div class="summary-total"><strong>Total</strong><strong class="mono">${{ number_format($cart['total'] / 100, 2) }}</strong></div>{!! $slot !!}
</div>
