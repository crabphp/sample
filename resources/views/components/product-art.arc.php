@props(string $category, string $name = '', string $slot = '')
<?php $item = strtolower($name); ?>
<div class="product-art" aria-hidden="true">
<svg viewBox="0 0 320 240" fill="none">
<ellipse cx="160" cy="202" rx="70" ry="8" class="art-shadow"/>
@if(str_contains($item, 'pen') && !str_contains($item, 'open'))
<path d="m109 182 66-127 19 10-66 127-22 12z" class="art-body"/>
<path d="m175 55 6-11 19 10-6 11m-79 115 18 10m-27 13 4-21" class="art-lines"/>
<path d="m189 76-25 48" class="art-highlight"/>
@elseif(str_contains($item, 'clip'))
<rect x="98" y="94" width="44" height="68" rx="14" class="art-body"/>
<rect x="176" y="94" width="44" height="68" rx="14" class="art-body"/>
<path d="M119 94V65c0-26 77-26 77 0v29m-77 68v30m77-30v30" class="art-outline" stroke-width="6"/>
@elseif(str_contains($item, 'apron'))
<path d="M137 62c-18-53 63-53 46 0" class="art-outline" stroke-width="5"/>
<path d="M132 62h56c-5 31 4 45 18 49l12 85H102l12-85c14-4 23-18 18-49z" class="art-body"/>
<path d="M134 132h52v31h-52z" class="art-top"/>
<path d="m114 111-26 11m118-11 26 11" class="art-lines"/>
@elseif(str_contains($item, 'kettle'))
<path d="M124 97c-12-88 99-88 80 0" class="art-outline" stroke-width="9"/>
<path d="M117 95h86l15 85c-4 25-112 25-115 0z" class="art-body"/>
<path d="M113 135c-26-4-34-29-49-28l8 10c10 18 9 47 34 49" class="art-body"/>
<ellipse cx="161" cy="95" rx="44" ry="11" class="art-top"/>
<path d="M155 82h13v10h-13" class="art-body"/>
@elseif(str_contains($item, 'skillet'))
<ellipse cx="143" cy="147" rx="66" ry="41" class="art-body"/>
<ellipse cx="143" cy="139" rx="65" ry="36" class="art-top"/>
<path d="m195 118 54-38c11-6 20 9 10 16l-51 42" class="art-body"/>
@elseif(str_contains($item, 'oven') || str_contains($item, 'cellar'))
<path d="M107 119H85v23h22m106-23h22v23h-22" class="art-outline" stroke-width="8"/>
<path d="M104 113h112v56c0 36-112 36-112 0z" class="art-body"/>
<ellipse cx="160" cy="113" rx="56" ry="15" class="art-top"/>
<path d="M145 94h30v13h-30z" class="art-body"/>
@elseif(str_contains($item, 'press'))
<path d="M198 101h21v68h-21" class="art-outline" stroke-width="10"/>
<path d="M116 88h82v103h-82z" class="art-body"/>
<ellipse cx="157" cy="88" rx="41" ry="10" class="art-top"/>
<path d="M157 88V60m-9-3h18m-48 74h78m-72-27v62" class="art-lines"/>
@elseif(str_contains($item, 'bottle'))
<path d="M142 63h36v26c3 8 15 11 15 27v72c0 17-66 17-66 0v-72c0-16 12-19 15-27z" class="art-body"/>
<rect x="140" y="51" width="40" height="16" rx="4" class="art-top"/>
<path d="M139 118v55" class="art-highlight"/>
@elseif(str_contains($item, 'board'))
<path d="M140 56v30H96v108h128V86h-44V56c0-20-40-20-40 0z" class="art-body"/>
<circle cx="160" cy="59" r="7" class="art-top"/>
<path d="M111 101v79m16-79v79m17-79v79m17-79v79m17-79v79m17-79v79m17-79v79" class="art-lines"/>
@elseif(str_contains($item, 'lamp'))
<path d="M160 110v82m-42 4h85" class="art-outline" stroke-width="8"/>
<path d="m128 62-34 59h132l-34-59z" class="art-body"/>
<ellipse cx="160" cy="62" rx="32" ry="7" class="art-top"/>
@elseif(str_contains($item, 'stand'))
<path d="m85 93 136-16 23 23-136 17z" class="art-top"/>
<path d="M108 117v68l12-2v-56l102-13v56l12-2v-66z" class="art-body"/>
@elseif(str_contains($item, 'mat') || str_contains($item, 'pad'))
<path d="m85 98 139-17 30 91-140 20z" class="art-body"/>
<path d="m96 105 119-14 24 74-119 17z" class="art-lines"/>
@elseif(str_contains($item, 'blanket') || str_contains($item, 'towel') || str_contains($item, 'hammock'))
<path d="m88 97 127-15 20 103-128 16z" class="art-body"/>
<path d="m88 97 20 16 127-16m-123 19 9 67m-17-55 129-16m-125 38 129-16m-126 37 129-16" class="art-lines"/>
<path d="m110 198 1 10m14-12 1 10m14-12 1 10m14-12 1 10m14-12 1 10m14-12 1 10m14-12 1 10m14-12 1 10" class="art-lines"/>
@elseif(str_contains($item, 'knife'))
<path d="m120 148 83-91c15 43-12 74-57 115z" class="art-top"/>
<path d="m125 143 24 23-46 37-24-23z" class="art-body"/>
<circle cx="111" cy="173" r="4" class="art-top"/>
@elseif(str_contains($item, 'candle'))
<path d="M118 98h84v85c0 20-84 20-84 0z" class="art-body"/>
<ellipse cx="160" cy="98" rx="42" ry="11" class="art-top"/>
<path d="M160 96v-8m0-8c-23-12 5-24 0-41 22 21 14 34 0 41" class="art-lines"/>
<rect x="136" y="125" width="48" height="38" rx="1" class="art-highlight"/>
@elseif(str_contains($item, 'pillow') || str_contains($item, 'cushion'))
<path d="M95 79c38 7 92 7 130 0-5 32-5 79 0 111-38-7-92-7-130 0 5-32 5-79 0-111z" class="art-body"/>
<path d="m103 88 14 15m99-15-14 15m-99 78 14-15m99 15-14-15" class="art-lines"/>
@elseif(str_contains($item, 'clock'))
<circle cx="160" cy="127" r="70" class="art-body"/>
<circle cx="160" cy="127" r="61" class="art-top"/>
<path d="M160 83v44l29 13m-29-76v9m63 54h-9m-54 63v-9m-63-54h9" class="art-lines"/>
@elseif(str_contains($item, 'basket'))
<path d="M117 95c0-74 86-74 86 0" class="art-outline" stroke-width="7"/>
<path d="m100 101 16 87c3 17 85 17 88 0l16-87z" class="art-body"/>
<ellipse cx="160" cy="101" rx="60" ry="12" class="art-top"/>
<path d="M107 125h106m-101 20h97m-93 20h89m-78-52 10 79m18-79v80m28-80-10 79" class="art-lines"/>
@elseif(str_contains($item, 'incense'))
<ellipse cx="160" cy="178" rx="77" ry="15" class="art-body"/>
<path d="m125 171 69-109" class="art-outline" stroke-width="3"/>
@elseif(str_contains($item, 'beanie'))
<path d="M105 157V126c0-88 110-88 110 0v31z" class="art-body"/>
<rect x="102" y="151" width="116" height="40" rx="5" class="art-top"/>
<path d="M128 92v57m22-73v73m22-73v73m22-57v57" class="art-lines"/>
@elseif(str_contains($item, 'sock'))
<path d="M110 69h38v78l-32 37c-20 19-44-5-27-23l21-23zm63 0h38v78l-32 37c-20 19-44-5-27-23l21-23z" class="art-body"/>
<path d="M110 88h38m25 0h38m-99 53 25 19m38-19 25 19" class="art-lines"/>
@elseif(str_contains($item, 'belt'))
<ellipse cx="160" cy="136" rx="76" ry="46" class="art-body"/>
<ellipse cx="160" cy="129" rx="63" ry="32" class="art-top"/>
<rect x="137" y="159" width="48" height="30" rx="4" class="art-body"/>
<path d="M144 167h34v14h-34zm0 7h25" class="art-lines"/>
@elseif(str_contains($item, 'tote'))
<path d="M130 86V65c0-40 60-40 60 0v21" class="art-outline" stroke-width="7"/>
<path d="m105 86 4 106h102l4-106z" class="art-body"/>
<path d="M129 86v24m62-24v24" class="art-lines"/>
@elseif($category === 'kitchen')
<path d="M199 100h18c34 0 34 51 0 51h-18" class="art-outline" stroke-width="12"/>
<path d="M104 81h99l-7 100c-2 24-83 24-85 0z" class="art-body"/>
<ellipse cx="153.5" cy="81" rx="49.5" ry="12" class="art-top"/>
<path d="M121 108l4 63" class="art-highlight"/>
@elseif($category === 'desk')
<path d="m94 71 108-18 31 128-109 20z" class="art-body"/>
<path d="m94 71 11 4 30 125-11 1z" class="art-top"/>
<path d="m120 93 60-10m-56 26 60-10m-56 26 60-10m-56 26 43-7" class="art-lines"/>
@elseif($category === 'outdoors')
<path d="M135 67V54c0-18 50-18 50 0v13" class="art-outline" stroke-width="7"/>
<rect x="103" y="61" width="114" height="135" rx="30" class="art-body"/>
<path d="M103 106h114" class="art-lines"/>
<rect x="123" y="133" width="74" height="43" rx="8" class="art-top"/>
<path d="M145 91h30m-15-7v14" class="art-highlight"/>
@elseif($category === 'apparel')
<path d="m127 56-29 12-38 62 35 22 22-37v80h87v-80l22 37 35-22-38-62-29-12-34 20z" class="art-body"/>
<path d="m127 56 33 20 34-20-13 34-21-14-21 14z" class="art-top"/>
<path d="M160 90v105m-37-61h25m25 0h25" class="art-lines"/>
@else
<path d="M135 62h50l-5 49c3 19 36 32 30 60-7 34-93 34-100 0-6-28 27-41 30-60z" class="art-body"/>
<ellipse cx="160" cy="62" rx="25" ry="7" class="art-top"/>
<path d="M128 151c-11 16-2 26 7 31" class="art-highlight"/>
@endif
</svg>{!! $slot !!}</div>
