@props(Crab\Contracts\Database\Page $page, array $links = [], string $path = '/products', string $slot = '')
<nav class="pagination" aria-label="Pagination">
<span class="small muted">Page {{ $page->currentPage }} of {{ max(1, $page->lastPage ?? 1) }}</span>
<div>
@if($links !== [])
@foreach($links as $link)<a class="page-link" href="{{ $link['url'] }}" @if($link['number'] === $page->currentPage) aria-current="page" @endif>{{ $link['number'] }}</a>
@endforeach
@else
@if($page->currentPage > 1)<a class="page-link" href="{{ $path }}?page={{ $page->currentPage - 1 }}">Previous</a>
@endif
@if($page->hasMore)<a class="page-link" href="{{ $path }}?page={{ $page->currentPage + 1 }}">Next</a>
@endif
@endif
</div>{!! $slot !!}</nav>
