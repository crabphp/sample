@props(string $category, string $slot = '')
<div class="product-art" aria-hidden="true">
<svg viewBox="0 0 320 240" fill="none"><ellipse cx="160" cy="202" rx="70" ry="8" class="art-shadow"/>
@if($category === 'kitchen')
<path d="M199 100h18c34 0 34 51 0 51h-18" class="art-outline" stroke-width="12"/><path d="M104 81h99l-7 100c-2 24-83 24-85 0z" class="art-body"/><ellipse cx="153.5" cy="81" rx="49.5" ry="12" class="art-top"/><path d="M121 108l4 63" class="art-highlight"/>
@elseif($category === 'desk')
<path d="m94 71 108-18 31 128-109 20z" class="art-body"/><path d="m94 71 11 4 30 125-11 1z" class="art-top"/><path d="m120 93 60-10m-56 26 60-10m-56 26 60-10m-56 26 43-7" class="art-lines"/>
@elseif($category === 'outdoors')
<path d="M135 67V54c0-18 50-18 50 0v13" class="art-outline" stroke-width="7"/><rect x="103" y="61" width="114" height="135" rx="30" class="art-body"/><path d="M103 106h114" class="art-lines"/><rect x="123" y="133" width="74" height="43" rx="8" class="art-top"/><path d="M145 91h30m-15-7v14" class="art-highlight"/>
@elseif($category === 'apparel')
<path d="m127 56-29 12-38 62 35 22 22-37v80h87v-80l22 37 35-22-38-62-29-12-34 20z" class="art-body"/><path d="m127 56 33 20 34-20-13 34-21-14-21 14z" class="art-top"/><path d="M160 90v105m-37-61h25m25 0h25" class="art-lines"/>
@else
<path d="M135 62h50l-5 49c3 19 36 32 30 60-7 34-93 34-100 0-6-28 27-41 30-60z" class="art-body"/><ellipse cx="160" cy="62" rx="25" ry="7" class="art-top"/><path d="M128 151c-11 16-2 26 7 31" class="art-highlight"/>
@endif
</svg>{!! $slot !!}</div>
