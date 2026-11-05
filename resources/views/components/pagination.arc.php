@props(Crab\Contracts\Database\Page $page, array $links, string $slot = '')
@if($links !== [])
<nav class="pagination" aria-label="Catalogue pages">
    @foreach($links as $link)
        <a href="{{ $link['url'] }}" @if($link['number'] === $page->currentPage) class="current" aria-current="page" @endif>{{ $link['number'] }}</a>
    @endforeach
</nav>
@endif
{!! $slot !!}
