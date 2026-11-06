@props(string $caption, string $head, string $slot = '')
<div class="table-wrap" tabindex="0" role="region" aria-label="{{ $caption }}">
<table><caption class="sr-only">{{ $caption }}</caption><thead>{!! $head !!}</thead><tbody>{!! $slot !!}</tbody></table>
</div>
