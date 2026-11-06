@props(?string $message = null, string $slot = '')
@if($message !== null)<div class="flash" role="status">
<span aria-hidden="true">✓</span> {{ $message }}{!! $slot !!}</div>
@endif
