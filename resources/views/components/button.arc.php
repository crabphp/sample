@props(string $variant = 'primary', string $href = '', array $attributes = [], string $slot = '')
<?php $kind = in_array($variant, ['primary', 'secondary', 'ghost', 'danger'], true) ? $variant : 'primary'; ?>
@if($href !== '')
<a @attributes($attributes, ['class' => "button button-{$kind}", 'href' => $href])>{!! $slot !!}</a>
@else
<button @attributes($attributes, ['class' => "button button-{$kind}", 'type' => 'submit'])>{!! $slot !!}</button>
@endif
