@props(array $attributes = [], string $slot = '')
<span @attributes($attributes, ['class' => 'badge'])>{!! $slot !!}</span>
