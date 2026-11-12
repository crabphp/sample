@props(string $title = '', string $eyebrow = '', array $attributes = [], string $slot = '', string $footer = '')
<section @attributes($attributes, ['class' => 'card'])>
@if($eyebrow !== '')<p class="eyebrow">{{ $eyebrow }}</p>
@endif
@if($title !== '')<h2>{{ $title }}</h2>
@endif
{!! $slot !!}
@if($footer !== '')<div class="card-footer">{!! $footer !!}</div>
@endif
</section>
