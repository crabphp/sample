@props(string $variant = 'primary', string $href = '', string $type = 'submit', bool $disabled = false, string $action = '', string $slot = '')
<?php $kind = in_array($variant, ['primary', 'secondary', 'ghost', 'danger'], true) ? $variant : 'primary'; ?>
@if($href !== '')
<a class="button button-{{ $kind }}" href="{{ $href }}">{!! $slot !!}</a>
@else
<button class="button button-{{ $kind }}" type="{{ $type }}" @if($disabled) disabled @endif @if($action !== '') formaction="{{ $action }}" @endif>{!! $slot !!}</button>
@endif
