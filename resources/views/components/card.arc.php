@props(string $title = '', string $eyebrow = '', string $slot = '', string $footer = '')
<section class="card">
@if($eyebrow !== '')<p class="eyebrow">{{ $eyebrow }}</p>@endif
@if($title !== '')<h2>{{ $title }}</h2>@endif
{!! $slot !!}
@if($footer !== '')<div class="card-footer">{!! $footer !!}</div>@endif
</section>
